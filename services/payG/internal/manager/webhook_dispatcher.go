package manager

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"net/http"
	"sync"
	"time"

	"fastcargo/payg/internal/core"
)

type WebhookDispatcher struct {
	mu           sync.RWMutex
	webhookURL   string
	sharedSecret string
	httpClient   *http.Client
}

func NewWebhookDispatcher(webhookURL, sharedSecret string) *WebhookDispatcher {
	return &WebhookDispatcher{
		webhookURL:   webhookURL,
		sharedSecret: sharedSecret,
		httpClient: &http.Client{
			Timeout: 10 * time.Second,
		},
	}
}

// SetSharedSecret safely updates the HMAC secret key
func (wd *WebhookDispatcher) SetSharedSecret(secret string) {
	wd.mu.Lock()
	defer wd.mu.Unlock()
	wd.sharedSecret = secret
}

// ComputeSignature calculates HMAC-SHA256 hex string over body
func (wd *WebhookDispatcher) ComputeSignature(body []byte) string {
	wd.mu.RLock()
	secret := wd.sharedSecret
	wd.mu.RUnlock()

	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write(body)
	return hex.EncodeToString(mac.Sum(nil))
}

// DispatchSettlement sends signed settlement notification to Laravel
func (wd *WebhookDispatcher) DispatchSettlement(ctx context.Context, payload core.SettlementWebhookPayload) error {
	body, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("failed to marshal webhook payload: %w", err)
	}

	sig := wd.ComputeSignature(body)
	timestamp := time.Now().UTC().Format(time.RFC3339)

	req, err := http.NewRequestWithContext(ctx, "POST", wd.webhookURL, bytes.NewReader(body))
	if err != nil {
		return fmt.Errorf("failed to create webhook request: %w", err)
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-PayG-Signature", "sha256="+sig)
	req.Header.Set("X-PayG-Timestamp", timestamp)
	req.Header.Set("User-Agent", "FastCargo-PayG-Dispatcher/1.0")

	resp, err := wd.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("failed to deliver webhook to %s: %w", wd.webhookURL, err)
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 400 {
		return fmt.Errorf("webhook endpoint %s returned non-2xx status: %d", wd.webhookURL, resp.StatusCode)
	}

	return nil
}
