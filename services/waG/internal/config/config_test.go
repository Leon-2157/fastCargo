package config

import (
	"encoding/json"
	"golang.org/x/crypto/bcrypt"
	"strings"
	"testing"
)

func TestDashboardSnapshotDoesNotExposeCredentials(t *testing.T) {
	c := &Config{
		GatewaySecret:              "gateway-secret-should-not-leak",
		WhatsAppCloudAccessToken:   "meta-token-should-not-leak",
		WhatsAppAppSecret:          "meta-secret-should-not-leak",
		WhatsAppWebhookVerifyToken: "verify-token-should-not-leak",
		AdminPasswordHash:          "bcrypt-hash-should-not-leak",
		AiApiKey:                   "ai-key-should-not-leak",
	}
	encoded, err := json.Marshal(c.DashboardSnapshot())
	if err != nil {
		t.Fatal(err)
	}
	for _, secret := range []string{c.GatewaySecret, c.WhatsAppCloudAccessToken, c.WhatsAppAppSecret, c.WhatsAppWebhookVerifyToken, c.AdminPasswordHash, c.AiApiKey} {
		if strings.Contains(string(encoded), secret) {
			t.Fatalf("dashboard snapshot exposed a credential")
		}
	}
	if !strings.Contains(string(encoded), `"ai_api_key_configured":true`) {
		t.Fatal("dashboard should indicate whether an AI key is configured")
	}
}

func TestProductionValidationFailsClosedWithoutAdminHash(t *testing.T) {
	c := &Config{AppEnv: "production"}
	if err := c.ValidateForProduction(); err == nil || !strings.Contains(err.Error(), "ADMIN_PASSWORD_HASH") {
		t.Fatalf("expected missing admin hash to block production, got %v", err)
	}
}

func TestProductionValidationAcceptsCompleteCloudConfiguration(t *testing.T) {
	hash, err := bcrypt.GenerateFromPassword([]byte("example-long-password"), 10)
	if err != nil {
		t.Fatal(err)
	}
	c := &Config{
		AppEnv: "production", AppName: "Test Gateway", Port: 3000,
		GatewaySecret: strings.Repeat("g", 48), AdminPasswordHash: string(hash),
		WhatsAppMode: "cloud", WhatsAppCloudAccessToken: strings.Repeat("t", 48),
		WhatsAppPhoneNumberID: "123456789012345", WhatsAppAppSecret: strings.Repeat("a", 32),
		WhatsAppWebhookVerifyToken: strings.Repeat("v", 32), WhatsAppGraphAPIVersion: "v21.0",
		WhatsAppOTPTemplateDefault: "otp_default", WhatsAppOTPTemplateReset: "otp_reset", WhatsAppOTPLanguage: "id",
		OtpExpiryMinutes: 5, OtpMaxAttempts: 5, OtpCooldownSeconds: 60,
		AiMaxContextTurns: 8, AiContextTtlMinutes: 30, AiDebounceSeconds: 2.5,
		AiHumanTakeoverMin: 30, AiTypingDelayUnit: "ms",
	}
	if err := c.ValidateForProduction(); err != nil {
		t.Fatalf("valid Cloud API config rejected: %v", err)
	}
	c.WhatsAppOTPTemplateDefault = ""
	if err := c.ValidateForProduction(); err == nil {
		t.Fatal("missing OTP template should block production")
	}
}

func TestHTPasswdBcryptHashPrefixCompatibility(t *testing.T) {
	hash, err := bcrypt.GenerateFromPassword([]byte("example-long-password"), 10)
	if err != nil {
		t.Fatal(err)
	}
	if strings.HasPrefix(string(hash), "$2a$") {
		hash[2] = 'y'
	}
	if err := bcrypt.CompareHashAndPassword(hash, []byte("example-long-password")); err != nil {
		t.Fatalf("bcrypt $2y$ hash from htpasswd is not accepted: %v", err)
	}
}
