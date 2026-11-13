package config

import (
	"os"
	"strconv"
)

type Config struct {
	Port               string
	HTTPBindAddress    string
	AppName            string
	AppEnv             string
	SharedSecret       string
	WebhookURL         string
	StoragePath        string
	DefaultEngineMode  string
	SobpayUsername     string
	SgpayStaticQR      string
	PollIntervalSecond int
}

func Load() *Config {
	return &Config{
		Port:               getEnv("PORT", "8085"),
		HTTPBindAddress:    getEnv("HTTP_BIND_ADDRESS", "0.0.0.0"),
		AppName:            getEnv("APP_NAME", "FastCargo PayG Station"),
		AppEnv:             getEnv("APP_ENV", "development"),
		SharedSecret:       getEnv("SHARED_SECRET", "fastcargo-payg-secret-2026"),
		WebhookURL:         getEnv("WEBHOOK_URL", "http://nginx/api/internal/payg/webhook"),
		StoragePath:        getEnv("SESSION_STORAGE_PATH", "./storage"),
		DefaultEngineMode:  getEnv("DEFAULT_ENGINE_MODE", "auto"),
		SobpayUsername:     getEnv("SOBPAY_CREATOR_USERNAME", ""),
		SgpayStaticQR:      getEnv("SGPAY_STATIC_QRIS", ""),
		PollIntervalSecond: getEnvInt("POLL_INTERVAL_SECONDS", 10),
	}
}

func getEnv(key, fallback string) string {
	if val, ok := os.LookupEnv(key); ok && val != "" {
		return val
	}
	return fallback
}

func getEnvInt(key string, fallback int) int {
	if val, ok := os.LookupEnv(key); ok && val != "" {
		if parsed, err := strconv.Atoi(val); err == nil {
			return parsed
		}
	}
	return fallback
}
