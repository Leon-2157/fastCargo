package api

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"path/filepath"
	"sync"
	"testing"
	"time"
)

func TestWebhookSignatureValidation(t *testing.T) {
	secret := "test-app-secret"
	body := []byte(`{"entry":[]}`)
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write(body)
	valid := "sha256=" + hex.EncodeToString(mac.Sum(nil))
	if !validWebhookSignature(secret, valid, body) {
		t.Fatal("valid signature rejected")
	}
	if validWebhookSignature(secret, valid, []byte(`{"entry":[1]}`)) {
		t.Fatal("modified body accepted")
	}
	if validWebhookSignature(secret, "sha1="+hex.EncodeToString(mac.Sum(nil)), body) {
		t.Fatal("wrong signature algorithm accepted")
	}
	if validWebhookSignature("", valid, body) {
		t.Fatal("empty app secret accepted")
	}
	if validWebhookSignature(secret, "sha256=not-hex", body) {
		t.Fatal("malformed signature accepted")
	}
}

func TestSupportedDeliveryStatusSkipsUnknownProviderEvents(t *testing.T) {
	for _, status := range []string{"sent", "delivered", "read", "failed"} {
		if !supportedDeliveryStatus(status) {
			t.Errorf("known delivery status %q was rejected", status)
		}
	}
	for _, status := range []string{"", "deleted", "unknown"} {
		if supportedDeliveryStatus(status) {
			t.Errorf("unsupported delivery status %q should be skipped", status)
		}
	}
}

func TestIPRateLimiterIsSafeUnderConcurrentRequests(t *testing.T) {
	limiter := ipRateLimiter{buckets: make(map[string]rateBucket)}
	const limit = 20
	var accepted int
	var mu sync.Mutex
	var wg sync.WaitGroup
	for i := 0; i < 100; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			if limiter.allow("concurrent", limit, time.Minute, time.Unix(1, 0)) {
				mu.Lock()
				accepted++
				mu.Unlock()
			}
		}()
	}
	wg.Wait()
	if accepted != limit {
		t.Fatalf("accepted %d requests; want exactly %d", accepted, limit)
	}
}

func TestIPRateLimiterScopesAndWindows(t *testing.T) {
	limiter := ipRateLimiter{buckets: make(map[string]rateBucket)}
	now := time.Unix(100, 0)
	if !limiter.allow("otp:127.0.0.1", 2, time.Minute, now) {
		t.Fatal("first request should pass")
	}
	if !limiter.allow("otp:127.0.0.1", 2, time.Minute, now.Add(time.Second)) {
		t.Fatal("second request should pass")
	}
	if limiter.allow("otp:127.0.0.1", 2, time.Minute, now.Add(2*time.Second)) {
		t.Fatal("request over the limit should fail")
	}
	if !limiter.allow("login:127.0.0.1", 2, time.Minute, now.Add(2*time.Second)) {
		t.Fatal("separate scope should have an independent bucket")
	}
	if !limiter.allow("otp:127.0.0.1", 2, time.Minute, now.Add(time.Minute)) {
		t.Fatal("request should pass after the window")
	}
	reachedLimit := false
	for i := 0; i < 10010; i++ {
		key := fmt.Sprintf("unique:%d", i)
		if !limiter.allow(key, 1, time.Minute, now) {
			reachedLimit = true
			break
		}
	}
	if !reachedLimit {
		t.Fatal("limiter did not enforce its bucket cap")
	}
	if limiter.allow("overflow", 1, time.Minute, now) {
		t.Fatal("limiter should fail closed when its bucket cap is reached")
	}
}

func TestInternalStatusAndQrRequireGatewaySecret(t *testing.T) {
	secret := "test-gateway-secret-0123456789abcdef"
	t.Setenv("APP_ENV", "development")
	t.Setenv("CONFIG_FILE", "")
	t.Setenv("GATEWAY_SECRET", secret)
	t.Setenv("SESSION_DB_PATH", filepath.Join(t.TempDir(), "wa-gateway-api-test.db"))

	router := SetupRouter()

	for _, path := range []string{"/internal/status", "/qr"} {
		req := httptest.NewRequest(http.MethodGet, path, nil)
		rec := httptest.NewRecorder()
		router.ServeHTTP(rec, req)
		if rec.Code != http.StatusUnauthorized {
			t.Fatalf("%s without secret returned HTTP %d, want 401", path, rec.Code)
		}
	}

	reqPublic := httptest.NewRequest(http.MethodGet, "/status", nil)
	recPublic := httptest.NewRecorder()
	router.ServeHTTP(recPublic, reqPublic)
	if recPublic.Code != http.StatusOK {
		t.Fatalf("public /status returned HTTP %d, want 200", recPublic.Code)
	}
	var publicBody map[string]any
	if err := json.Unmarshal(recPublic.Body.Bytes(), &publicBody); err != nil {
		t.Fatal(err)
	}
	for _, sensitive := range []string{"qr", "pairing_code", "user"} {
		if _, leaked := publicBody[sensitive]; leaked {
			t.Fatalf("public /status unexpectedly included %q", sensitive)
		}
	}

	reqInternal := httptest.NewRequest(http.MethodGet, "/internal/status", nil)
	reqInternal.Header.Set("X-Gateway-Secret", secret)
	recInternal := httptest.NewRecorder()
	router.ServeHTTP(recInternal, reqInternal)
	if recInternal.Code != http.StatusOK {
		t.Fatalf("/internal/status with secret returned HTTP %d, want 200", recInternal.Code)
	}
	var internalBody map[string]any
	if err := json.Unmarshal(recInternal.Body.Bytes(), &internalBody); err != nil {
		t.Fatal(err)
	}
	for _, key := range []string{"service", "status", "paired", "ready"} {
		if _, ok := internalBody[key]; !ok {
			t.Fatalf("/internal/status missing field %q", key)
		}
	}

	reqQr := httptest.NewRequest(http.MethodGet, "/qr", nil)
	reqQr.Header.Set("X-Gateway-Secret", secret)
	recQr := httptest.NewRecorder()
	router.ServeHTTP(recQr, reqQr)
	if recQr.Code != http.StatusNotFound && recQr.Code != http.StatusOK {
		t.Fatalf("/qr with secret returned unexpected HTTP %d", recQr.Code)
	}
}
