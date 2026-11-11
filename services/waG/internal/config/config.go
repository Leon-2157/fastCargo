package config

import (
	"fmt"
	"math"
	"net"
	"net/url"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"

	"github.com/joho/godotenv"
	"golang.org/x/crypto/bcrypt"
)

type Config struct {
	mu sync.RWMutex

	// Server
	Port          int    `json:"port"`
	AppName       string `json:"app_name"`
	AppEnv        string `json:"app_env"`
	GatewaySecret string `json:"-"`

	// WhatsApp Business Cloud API. The unofficial WhatsApp Web client is
	// retained for isolated development only and is rejected in production.
	WhatsAppMode               string `json:"whatsapp_mode"`
	WhatsAppCloudAccessToken   string `json:"-"`
	WhatsAppPhoneNumberID      string `json:"-"`
	WhatsAppAppSecret          string `json:"-"`
	WhatsAppWebhookVerifyToken string `json:"-"`
	WhatsAppGraphAPIVersion    string `json:"whatsapp_graph_api_version"`
	WhatsAppOTPLanguage        string `json:"whatsapp_otp_language"`
	WhatsAppOTPTemplateDefault string `json:"whatsapp_otp_template_default"`
	WhatsAppOTPTemplateReset   string `json:"whatsapp_otp_template_reset"`

	// Dashboard Auth
	DashboardPasswordDefault string `json:"-"`
	AdminPasswordHash        string `json:"-"`

	// Storage
	SessionDbPath string `json:"session_db_path"`

	// OTP Settings
	OtpExpiryMinutes   int    `json:"otp_expiry_minutes"`
	OtpMaxAttempts     int    `json:"otp_max_attempts"`
	OtpCooldownSeconds int    `json:"otp_cooldown_seconds"`
	OtpTemplateDefault string `json:"otp_template_default"`
	OtpTemplateReset   string `json:"otp_template_reset"`

	// AI Engine (Unified OpenAI-Compatible format)
	AiEnabled             bool    `json:"ai_enabled"`
	AiBaseURL             string  `json:"ai_base_url"`
	AiApiKey              string  `json:"-"`
	AiModel               string  `json:"ai_model"`
	AiKnowledgeBasePath   string  `json:"ai_knowledge_base_path"`
	AiMaxContextTurns     int     `json:"ai_max_context_turns"`
	AiContextTtlMinutes   int     `json:"ai_context_ttl_minutes"`
	AiDebounceSeconds     float64 `json:"ai_debounce_seconds"`
	AiFallbackMessage     string  `json:"ai_fallback_message"`
	AiHumanTakeoverEnable bool    `json:"ai_human_takeover_enabled"`
	AiHumanTakeoverMin    int     `json:"ai_human_takeover_minutes"`
	AiTypingDelayEnabled  bool    `json:"ai_typing_delay_enabled"`
	AiTypingDelayValue    int     `json:"ai_typing_delay_value"`
	AiTypingDelayUnit     string  `json:"ai_typing_delay_unit"`
	AiResponseTemplate    string  `json:"ai_response_template"`

	// External Webhook
	WebhookURL         string `json:"webhook_url"`
	CorsAllowedOrigins string `json:"-"`
	TrustedProxyCIDRs  string `json:"-"`
}

var (
	instance   *Config
	instanceMu sync.RWMutex
)

// Get returns the singleton config instance
func Get() *Config {
	instanceMu.RLock()
	cfg := instance
	instanceMu.RUnlock()
	if cfg != nil {
		return cfg
	}
	instanceMu.Lock()
	defer instanceMu.Unlock()
	if instance == nil {
		instance = loadConfig()
	}
	return instance
}

// Reload re-reads environment configuration into the shared config instance.
func Reload() *Config {
	instanceMu.Lock()
	instance = loadConfig()
	cfg := instance
	instanceMu.Unlock()
	return cfg
}

// Snapshot copies all live settings under the configuration lock. Callers
// should read this value instead of dereferencing fields while dashboard
// updates may be in progress.
func (c *Config) Snapshot() Config {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return Config{
		Port: c.Port, AppName: c.AppName, AppEnv: c.AppEnv, GatewaySecret: c.GatewaySecret,
		WhatsAppMode: c.WhatsAppMode, WhatsAppCloudAccessToken: c.WhatsAppCloudAccessToken,
		WhatsAppPhoneNumberID: c.WhatsAppPhoneNumberID, WhatsAppAppSecret: c.WhatsAppAppSecret,
		WhatsAppWebhookVerifyToken: c.WhatsAppWebhookVerifyToken, WhatsAppGraphAPIVersion: c.WhatsAppGraphAPIVersion,
		WhatsAppOTPLanguage: c.WhatsAppOTPLanguage, WhatsAppOTPTemplateDefault: c.WhatsAppOTPTemplateDefault,
		WhatsAppOTPTemplateReset: c.WhatsAppOTPTemplateReset,
		DashboardPasswordDefault: c.DashboardPasswordDefault, AdminPasswordHash: c.AdminPasswordHash,
		SessionDbPath: c.SessionDbPath, OtpExpiryMinutes: c.OtpExpiryMinutes, OtpMaxAttempts: c.OtpMaxAttempts,
		OtpCooldownSeconds: c.OtpCooldownSeconds, OtpTemplateDefault: c.OtpTemplateDefault, OtpTemplateReset: c.OtpTemplateReset,
		AiEnabled: c.AiEnabled, AiBaseURL: c.AiBaseURL, AiApiKey: c.AiApiKey, AiModel: c.AiModel,
		AiKnowledgeBasePath: c.AiKnowledgeBasePath, AiMaxContextTurns: c.AiMaxContextTurns,
		AiContextTtlMinutes: c.AiContextTtlMinutes, AiDebounceSeconds: c.AiDebounceSeconds,
		AiFallbackMessage: c.AiFallbackMessage, AiHumanTakeoverEnable: c.AiHumanTakeoverEnable,
		AiHumanTakeoverMin: c.AiHumanTakeoverMin, AiTypingDelayEnabled: c.AiTypingDelayEnabled,
		AiTypingDelayValue: c.AiTypingDelayValue, AiTypingDelayUnit: c.AiTypingDelayUnit,
		AiResponseTemplate: c.AiResponseTemplate, WebhookURL: c.WebhookURL, CorsAllowedOrigins: c.CorsAllowedOrigins,
		TrustedProxyCIDRs: c.TrustedProxyCIDRs,
	}
}

func loadConfig() *Config {
	// Try loading .env if exists
	_ = godotenv.Load(".env")
	if path := strings.TrimSpace(os.Getenv("CONFIG_FILE")); path != "" && path != ".env" {
		// Runtime override files are written by authenticated dashboard changes.
		// They intentionally take precedence over the deployment's initial env.
		_ = godotenv.Overload(path)
	}

	c := &Config{
		Port:          getIntEnv("PORT", 3000),
		AppName:       getStringEnv("APP_NAME", "RideOn Indonesia"),
		AppEnv:        getStringEnv("APP_ENV", "production"),
		GatewaySecret: getStringEnv("GATEWAY_SECRET", ""),

		WhatsAppMode:               getStringEnv("WHATSAPP_MODE", "cloud"),
		WhatsAppCloudAccessToken:   getStringEnv("WHATSAPP_CLOUD_ACCESS_TOKEN", ""),
		WhatsAppPhoneNumberID:      getStringEnv("WHATSAPP_PHONE_NUMBER_ID", ""),
		WhatsAppAppSecret:          getStringEnv("WHATSAPP_APP_SECRET", ""),
		WhatsAppWebhookVerifyToken: getStringEnv("WHATSAPP_WEBHOOK_VERIFY_TOKEN", ""),
		WhatsAppGraphAPIVersion:    getStringEnv("WHATSAPP_GRAPH_API_VERSION", ""),
		WhatsAppOTPLanguage:        getStringEnv("WHATSAPP_OTP_LANGUAGE", "id"),
		WhatsAppOTPTemplateDefault: getStringEnv("WHATSAPP_OTP_TEMPLATE_DEFAULT", ""),
		WhatsAppOTPTemplateReset:   getStringEnv("WHATSAPP_OTP_TEMPLATE_RESET", ""),

		DashboardPasswordDefault: getStringEnv("DASHBOARD_PASSWORD_DEFAULT", ""),
		AdminPasswordHash:        getStringEnv("ADMIN_PASSWORD_HASH", ""),

		SessionDbPath: getStringEnv("SESSION_DB_PATH", "./storage/whatsapp.db"),

		OtpExpiryMinutes:   getIntEnv("OTP_EXPIRY_MINUTES", 5),
		OtpMaxAttempts:     getIntEnv("OTP_MAX_ATTEMPTS", 5),
		OtpCooldownSeconds: getIntEnv("OTP_COOLDOWN_SECONDS", 60),
		OtpTemplateDefault: getStringEnv("OTP_TEMPLATE_DEFAULT", "*{{app_name}}*\n\nHalo! Kode verifikasi Anda adalah:\n\n*{{code}}*\n\nKode ini berlaku selama {{expiry_minutes}} menit. Demi keamanan akun Anda, jangan berikan kode ini kepada siapa pun."),
		OtpTemplateReset:   getStringEnv("OTP_TEMPLATE_RESET", "*{{app_name}}*\n\nHalo! Kode verifikasi untuk mengatur ulang kata sandi akun Anda adalah:\n\n*{{code}}*\n\nKode ini berlaku selama {{expiry_minutes}} menit. Jangan berikan kode ini kepada siapa pun."),

		AiEnabled:             getBoolEnv("AI_ENABLED", false),
		AiBaseURL:             getStringEnv("AI_BASE_URL", "https://generativelanguage.googleapis.com/v1beta/openai/"),
		AiApiKey:              getStringEnv("AI_API_KEY", ""),
		AiModel:               getStringEnv("AI_MODEL", "gemini-1.5-flash"),
		AiKnowledgeBasePath:   getStringEnv("AI_KNOWLEDGE_BASE_PATH", "./knowledge_base.md"),
		AiMaxContextTurns:     getIntEnv("AI_MAX_CONTEXT_TURNS", 8),
		AiContextTtlMinutes:   getIntEnv("AI_CONTEXT_TTL_MINUTES", 30),
		AiDebounceSeconds:     getFloatEnv("AI_DEBOUNCE_SECONDS", 2.5),
		AiFallbackMessage:     getStringEnv("AI_FALLBACK_MESSAGE", "Mohon maaf, asisten AI kami saat ini sedang melayani banyak pertanyaan. Pesan Anda telah kami catat untuk Admin kami."),
		AiHumanTakeoverEnable: getBoolEnv("AI_HUMAN_TAKEOVER_ENABLED", true),
		AiHumanTakeoverMin:    getIntEnv("AI_HUMAN_TAKEOVER_MINUTES", 30),
		AiTypingDelayEnabled:  getBoolEnv("AI_TYPING_DELAY_ENABLED", false),
		AiTypingDelayValue:    getIntEnv("AI_TYPING_DELAY_VALUE", 1500),
		AiTypingDelayUnit:     getStringEnv("AI_TYPING_DELAY_UNIT", "ms"),
		AiResponseTemplate:    getStringEnv("AI_RESPONSE_TEMPLATE", "{{reply}}"),

		WebhookURL:         getStringEnv("WEBHOOK_URL", ""),
		CorsAllowedOrigins: getStringEnv("CORS_ALLOWED_ORIGINS", ""),
		TrustedProxyCIDRs:  getStringEnv("TRUSTED_PROXY_CIDRS", ""),
	}

	// Ensure session dir exists
	dir := filepath.Dir(c.SessionDbPath)
	if dir != "" && dir != "." {
		_ = os.MkdirAll(dir, 0755)
	}

	return c
}

// ValidateForProduction rejects configurations that would silently weaken
// authentication, use the unofficial client, or run the production API without
// the credentials needed by the official WhatsApp Business Cloud API.
func (c *Config) ValidateForProduction() error {
	c.mu.RLock()
	defer c.mu.RUnlock()
	if !strings.EqualFold(c.AppEnv, "production") {
		return nil
	}
	if c.AdminPasswordHash == "" {
		return fmt.Errorf("ADMIN_PASSWORD_HASH wajib diisi untuk production")
	}
	if strings.TrimSpace(c.AppName) == "" || len([]rune(c.AppName)) > 80 {
		return fmt.Errorf("APP_NAME wajib diisi dan maksimal 80 karakter")
	}
	if cost, err := bcrypt.Cost([]byte(c.AdminPasswordHash)); err != nil || cost < 10 || cost > 14 {
		return fmt.Errorf("ADMIN_PASSWORD_HASH harus berupa bcrypt hash dengan cost 10 sampai 14")
	}
	if len(c.GatewaySecret) < 32 || c.GatewaySecret == "rideon-wa-secret-2026" {
		return fmt.Errorf("GATEWAY_SECRET wajib berupa secret acak dengan minimal 32 byte")
	}
	if c.WhatsAppMode != "cloud" {
		return fmt.Errorf("production hanya mendukung WHATSAPP_MODE=cloud; klien WhatsApp Web tidak didukung")
	}
	if len(c.WhatsAppCloudAccessToken) < 32 || c.WhatsAppPhoneNumberID == "" || c.WhatsAppAppSecret == "" || c.WhatsAppWebhookVerifyToken == "" {
		return fmt.Errorf("kredensial WhatsApp Cloud API belum lengkap")
	}
	if len(c.WhatsAppAppSecret) < 32 || len(c.WhatsAppWebhookVerifyToken) < 32 {
		return fmt.Errorf("WHATSAPP_APP_SECRET dan WHATSAPP_WEBHOOK_VERIFY_TOKEN harus berupa secret kuat minimal 32 byte")
	}
	if !validGraphVersion(c.WhatsAppGraphAPIVersion) {
		return fmt.Errorf("WHATSAPP_GRAPH_API_VERSION harus diisi dengan versi Graph API yang didukung, misalnya vNN.N")
	}
	if !digitsOnly(c.WhatsAppPhoneNumberID) {
		return fmt.Errorf("WHATSAPP_PHONE_NUMBER_ID harus berisi ID numerik")
	}
	if !validTemplateName(c.WhatsAppOTPTemplateDefault) || !validTemplateName(c.WhatsAppOTPTemplateReset) {
		return fmt.Errorf("nama template OTP Cloud API default dan reset wajib diisi")
	}
	if !validLanguageCode(c.WhatsAppOTPLanguage) {
		return fmt.Errorf("WHATSAPP_OTP_LANGUAGE tidak valid")
	}
	if c.DashboardPasswordDefault != "" {
		return fmt.Errorf("DASHBOARD_PASSWORD_DEFAULT tidak boleh dipakai di production; gunakan ADMIN_PASSWORD_HASH")
	}
	if c.Port < 1 || c.Port > 65535 {
		return fmt.Errorf("PORT harus berada pada rentang 1 sampai 65535")
	}
	if c.OtpExpiryMinutes < 1 || c.OtpExpiryMinutes > 60 || c.OtpMaxAttempts < 1 || c.OtpMaxAttempts > 10 || c.OtpCooldownSeconds < 10 || c.OtpCooldownSeconds > 3600 {
		return fmt.Errorf("pengaturan OTP di luar rentang aman")
	}
	if c.AiMaxContextTurns < 1 || c.AiMaxContextTurns > 20 || c.AiContextTtlMinutes < 1 || c.AiContextTtlMinutes > 1440 {
		return fmt.Errorf("batas konteks AI di luar rentang aman")
	}
	if math.IsNaN(c.AiDebounceSeconds) || math.IsInf(c.AiDebounceSeconds, 0) || c.AiDebounceSeconds < 0.2 || c.AiDebounceSeconds > 10 {
		return fmt.Errorf("AI_DEBOUNCE_SECONDS harus 0.2 sampai 10 detik")
	}
	if c.AiHumanTakeoverMin < 1 || c.AiHumanTakeoverMin > 180 || c.AiTypingDelayValue < 0 || c.AiTypingDelayValue > 10000 || (c.AiTypingDelayUnit != "ms" && c.AiTypingDelayUnit != "s") {
		return fmt.Errorf("pengaturan AI takeover atau jeda mengetik di luar rentang aman")
	}
	if len(c.OtpTemplateDefault) > 4000 || len(c.OtpTemplateReset) > 4000 || len(c.AiFallbackMessage) > 4000 || len(c.AiResponseTemplate) > 4000 {
		return fmt.Errorf("template pesan melebihi batas ukuran")
	}
	if c.AiEnabled {
		if c.AiApiKey == "" {
			return fmt.Errorf("AI_ENABLED=true tetapi AI_API_KEY belum dikonfigurasi; isi key atau nonaktifkan AI")
		}
		if strings.TrimSpace(c.AiModel) == "" {
			return fmt.Errorf("AI_MODEL wajib diisi jika AI diaktifkan")
		}
		u, err := url.Parse(c.AiBaseURL)
		if err != nil || u.Scheme != "https" || u.Host == "" || u.User != nil || u.Fragment != "" || u.RawQuery != "" {
			return fmt.Errorf("AI_BASE_URL production harus berupa URL HTTPS yang valid")
		}
	}
	if c.WebhookURL != "" {
		u, err := url.Parse(c.WebhookURL)
		if err != nil || u.Host == "" || u.User != nil || u.Fragment != "" || u.RawQuery != "" || len(c.WebhookURL) > 2048 || u.Scheme != "https" {
			return fmt.Errorf("WEBHOOK_URL production harus berupa URL HTTPS yang valid")
		}
	}
	for _, origin := range strings.Split(c.CorsAllowedOrigins, ",") {
		origin = strings.TrimSpace(origin)
		if origin == "" {
			continue
		}
		u, err := url.Parse(origin)
		if err != nil || (u.Scheme != "https" && u.Scheme != "http") || (strings.EqualFold(c.AppEnv, "production") && u.Scheme != "https") || u.Host == "" || u.Path != "" || u.RawQuery != "" || u.Fragment != "" || u.User != nil || origin == "*" {
			return fmt.Errorf("CORS_ALLOWED_ORIGINS berisi origin yang tidak valid")
		}
	}
	for _, cidr := range strings.Split(c.TrustedProxyCIDRs, ",") {
		cidr = strings.TrimSpace(cidr)
		if cidr == "" {
			continue
		}
		_, network, err := net.ParseCIDR(cidr)
		if err != nil || network == nil || network.String() == "0.0.0.0/0" || network.String() == "::/0" {
			return fmt.Errorf("TRUSTED_PROXY_CIDRS harus berisi CIDR proxy spesifik, bukan semua alamat")
		}
	}
	return nil
}

func validTemplateName(value string) bool {
	if value == "" {
		return false
	}
	for _, r := range value {
		if !(r >= 'a' && r <= 'z') && !(r >= '0' && r <= '9') && r != '_' {
			return false
		}
	}
	return true
}

func validLanguageCode(value string) bool {
	parts := strings.Split(value, "_")
	if len(parts) < 1 || len(parts) > 2 || len(parts[0]) != 2 {
		return false
	}
	for _, r := range parts[0] {
		if r < 'a' || r > 'z' {
			return false
		}
	}
	if len(parts) == 2 {
		if len(parts[1]) != 2 {
			return false
		}
		for _, r := range parts[1] {
			if r < 'A' || r > 'Z' {
				return false
			}
		}
	}
	return true
}

func digitsOnly(value string) bool {
	if value == "" {
		return false
	}
	for _, r := range value {
		if r < '0' || r > '9' {
			return false
		}
	}
	return true
}

func validGraphVersion(v string) bool {
	if len(v) < 5 || v[0] != 'v' {
		return false
	}
	dot := false
	for _, r := range v[1:] {
		if r == '.' && !dot {
			dot = true
			continue
		}
		if r < '0' || r > '9' {
			return false
		}
	}
	return dot && v[len(v)-1] != '.'
}

// FormatOtpMessage builds the formatted OTP text from template
func (c *Config) FormatOtpMessage(code, purpose string, customTemplate string) string {
	c.mu.RLock()
	defer c.mu.RUnlock()

	tpl := c.OtpTemplateDefault
	if customTemplate != "" {
		tpl = customTemplate
	} else if purpose == "reset" || purpose == "reset_password" {
		tpl = c.OtpTemplateReset
	}

	res := strings.ReplaceAll(tpl, "{{app_name}}", c.AppName)
	res = strings.ReplaceAll(res, "{{code}}", code)
	res = strings.ReplaceAll(res, "{{expiry_minutes}}", strconv.Itoa(c.OtpExpiryMinutes))
	return res
}

// FormatAiResponse formats the raw AI reply according to the configured template
func (c *Config) FormatAiResponse(reply string) string {
	c.mu.RLock()
	defer c.mu.RUnlock()

	tpl := strings.TrimSpace(c.AiResponseTemplate)
	if tpl == "" {
		return reply
	}

	res := strings.ReplaceAll(tpl, "{{app_name}}", c.AppName)
	res = strings.ReplaceAll(res, "{{reply}}", reply)
	return res
}

// UpdateAndPersist updates in-memory config and writes changes back to .env
func (c *Config) UpdateAndPersist(updates map[string]string) error {
	c.mu.Lock()
	defer c.mu.Unlock()

	// Read existing .env file into map
	path := strings.TrimSpace(os.Getenv("CONFIG_FILE"))
	if path == "" {
		path = ".env"
	}
	envMap, readErr := godotenv.Read(path)
	if readErr != nil {
		envMap = make(map[string]string)
		for _, item := range os.Environ() {
			if key, value, ok := strings.Cut(item, "="); ok && managedEnvKey(key) {
				envMap[key] = value
			}
		}
	}

	for k, v := range updates {
		envMap[k] = v
	}
	if err := writeEnvAtomically(envMap, path); err != nil {
		return err
	}
	for k, v := range updates {
		_ = os.Setenv(k, v)
		switch k {
		case "APP_NAME":
			c.AppName = v
		case "PORT":
			if p, err := strconv.Atoi(v); err == nil {
				c.Port = p
			}
		case "GATEWAY_SECRET":
			c.GatewaySecret = v
		case "ADMIN_PASSWORD_HASH":
			c.AdminPasswordHash = v
		case "AI_ENABLED":
			c.AiEnabled = v == "true" || v == "1"
		case "AI_BASE_URL":
			c.AiBaseURL = v
		case "AI_API_KEY":
			c.AiApiKey = v
		case "AI_MODEL":
			c.AiModel = v
		case "AI_FALLBACK_MESSAGE":
			c.AiFallbackMessage = v
		case "AI_HUMAN_TAKEOVER_ENABLED":
			c.AiHumanTakeoverEnable = v == "true" || v == "1"
		case "AI_HUMAN_TAKEOVER_MINUTES":
			if m, err := strconv.Atoi(v); err == nil {
				c.AiHumanTakeoverMin = m
			}
		case "AI_TYPING_DELAY_ENABLED":
			c.AiTypingDelayEnabled = v == "true" || v == "1"
		case "AI_TYPING_DELAY_VALUE":
			if dv, err := strconv.Atoi(v); err == nil {
				c.AiTypingDelayValue = dv
			}
		case "AI_TYPING_DELAY_UNIT":
			c.AiTypingDelayUnit = v
		case "AI_RESPONSE_TEMPLATE":
			c.AiResponseTemplate = v
		case "OTP_EXPIRY_MINUTES":
			if exp, err := strconv.Atoi(v); err == nil {
				c.OtpExpiryMinutes = exp
			}
		case "OTP_MAX_ATTEMPTS":
			if att, err := strconv.Atoi(v); err == nil {
				c.OtpMaxAttempts = att
			}
		case "OTP_COOLDOWN_SECONDS":
			if cooldown, err := strconv.Atoi(v); err == nil {
				c.OtpCooldownSeconds = cooldown
			}
		case "OTP_TEMPLATE_DEFAULT":
			c.OtpTemplateDefault = v
		case "OTP_TEMPLATE_RESET":
			c.OtpTemplateReset = v
		case "WEBHOOK_URL":
			c.WebhookURL = v
		}
	}

	return nil
}

func managedEnvKey(key string) bool {
	switch key {
	case "PORT", "APP_NAME", "APP_ENV", "GATEWAY_SECRET",
		"WHATSAPP_MODE", "WHATSAPP_CLOUD_ACCESS_TOKEN", "WHATSAPP_PHONE_NUMBER_ID", "WHATSAPP_APP_SECRET", "WHATSAPP_WEBHOOK_VERIFY_TOKEN", "WHATSAPP_GRAPH_API_VERSION", "WHATSAPP_OTP_LANGUAGE", "WHATSAPP_OTP_TEMPLATE_DEFAULT", "WHATSAPP_OTP_TEMPLATE_RESET",
		"DASHBOARD_PASSWORD_DEFAULT", "ADMIN_PASSWORD_HASH", "SESSION_DB_PATH",
		"OTP_EXPIRY_MINUTES", "OTP_MAX_ATTEMPTS", "OTP_COOLDOWN_SECONDS", "OTP_TEMPLATE_DEFAULT", "OTP_TEMPLATE_RESET",
		"AI_ENABLED", "AI_BASE_URL", "AI_API_KEY", "AI_MODEL", "AI_KNOWLEDGE_BASE_PATH", "AI_MAX_CONTEXT_TURNS", "AI_CONTEXT_TTL_MINUTES", "AI_DEBOUNCE_SECONDS", "AI_FALLBACK_MESSAGE", "AI_HUMAN_TAKEOVER_ENABLED", "AI_HUMAN_TAKEOVER_MINUTES", "AI_TYPING_DELAY_ENABLED", "AI_TYPING_DELAY_VALUE", "AI_TYPING_DELAY_UNIT", "AI_RESPONSE_TEMPLATE",
		"WEBHOOK_URL", "CORS_ALLOWED_ORIGINS", "TRUSTED_PROXY_CIDRS":
		return true
	default:
		return false
	}
}

func writeEnvAtomically(envMap map[string]string, path string) error {
	data, err := godotenv.Marshal(envMap)
	if err != nil {
		return err
	}
	dirName := filepath.Dir(path)
	if dirName == "" {
		dirName = "."
	}
	if err := os.MkdirAll(dirName, 0700); err != nil {
		return err
	}
	tmp, err := os.CreateTemp(dirName, ".env.tmp-*")
	if err != nil {
		return err
	}
	tmpName := tmp.Name()
	defer os.Remove(tmpName)
	if err = tmp.Chmod(0600); err == nil {
		_, err = tmp.WriteString(data)
	}
	if err == nil {
		err = tmp.Sync()
	}
	closeErr := tmp.Close()
	if err != nil {
		return err
	}
	if closeErr != nil {
		return closeErr
	}
	if err = os.Rename(tmpName, path); err != nil {
		return err
	}
	dir, err := os.Open(dirName)
	if err != nil {
		return err
	}
	defer dir.Close()
	return dir.Sync()
}

// DashboardSnapshot returns only non-secret settings and explicit secret
// presence flags for the authenticated dashboard.
func (c *Config) DashboardSnapshot() map[string]interface{} {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return map[string]interface{}{
		"app_name":                  c.AppName,
		"port":                      c.Port,
		"whatsapp_mode":             c.WhatsAppMode,
		"whatsapp_connected":        c.WhatsAppCloudAccessToken != "" && c.WhatsAppPhoneNumberID != "",
		"ai_enabled":                c.AiEnabled,
		"ai_base_url":               c.AiBaseURL,
		"ai_api_key":                "",
		"ai_api_key_configured":     c.AiApiKey != "",
		"clear_ai_api_key":          false,
		"ai_model":                  c.AiModel,
		"ai_fallback_message":       c.AiFallbackMessage,
		"ai_typing_delay_enabled":   c.AiTypingDelayEnabled,
		"ai_typing_delay_value":     c.AiTypingDelayValue,
		"ai_typing_delay_unit":      c.AiTypingDelayUnit,
		"ai_human_takeover_minutes": c.AiHumanTakeoverMin,
		"ai_response_template":      c.AiResponseTemplate,
		"otp_expiry_minutes":        c.OtpExpiryMinutes,
		"otp_max_attempts":          c.OtpMaxAttempts,
		"otp_cooldown_seconds":      c.OtpCooldownSeconds,
		"otp_template_default":      c.OtpTemplateDefault,
		"otp_template_reset":        c.OtpTemplateReset,
	}
}

// Helpers
func getStringEnv(key, def string) string {
	if val := os.Getenv(key); val != "" {
		return val
	}
	return def
}

func getIntEnv(key string, def int) int {
	if val := os.Getenv(key); val != "" {
		if i, err := strconv.Atoi(val); err == nil {
			return i
		}
	}
	return def
}

func getFloatEnv(key string, def float64) float64 {
	if val := os.Getenv(key); val != "" {
		if f, err := strconv.ParseFloat(val, 64); err == nil {
			return f
		}
	}
	return def
}

func getBoolEnv(key string, def bool) bool {
	if val := os.Getenv(key); val != "" {
		val = strings.ToLower(strings.TrimSpace(val))
		return val == "true" || val == "1" || val == "yes"
	}
	return def
}

func (c *Config) String() string {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return fmt.Sprintf("Config[Port: %d, AppName: %s, AI: %t, Model: %s]", c.Port, c.AppName, c.AiEnabled, c.AiModel)
}
