package manager

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"testing"
)

func TestWebhookSignature(t *testing.T) {
	secret := "test-secret-123"
	dispatcher := NewWebhookDispatcher("http://localhost/webhook", secret)

	body := []byte(`{"event":"payment.settled","transaction_ref":"REF-1"}`)
	sig := dispatcher.ComputeSignature(body)

	// Verify with standard crypto/hmac
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write(body)
	expected := hex.EncodeToString(mac.Sum(nil))

	if sig != expected {
		t.Fatalf("signature mismatch: got %s, expected %s", sig, expected)
	}
}
