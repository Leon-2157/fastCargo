package api

import (
	"context"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"

	"github.com/gin-gonic/gin"

	"wa-gateway/internal/ai"
	"wa-gateway/internal/audit"
	"wa-gateway/internal/config"
	"wa-gateway/internal/dashboard"
	"wa-gateway/internal/whatsapp"
)

type LoginRequest struct {
	Password string `json:"password"`
}

type PairingCodeRequest struct {
	Phone string `json:"phone"`
}

type KnowledgeBaseRequest struct {
	Content string `json:"content"`
}

type AiSimulateRequest struct {
	Message string `json:"message"`
}

type ChangePasswordRequest struct {
	OldPassword string `json:"old_password"`
	NewPassword string `json:"new_password"`
}

func handleDashboardLogin(c *gin.Context) {
	var req LoginRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "Password diperlukan"})
		return
	}

	if !dashboard.GetAuth().VerifyPassword(req.Password) {
		c.JSON(http.StatusUnauthorized, gin.H{"success": false, "error": "Kata sandi salah"})
		return
	}

	token, err := dashboard.GetAuth().CreateSession()
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"success": false, "error": "Gagal membuat sesi"})
		return
	}
	maxAge := int((7 * 24 * time.Hour).Seconds())
	c.SetCookie("gw_session", token, maxAge, "/api/dashboard", "", config.Get().Snapshot().AppEnv == "production", true)
	c.SetSameSite(http.SameSiteStrictMode)
	c.JSON(http.StatusOK, gin.H{"success": true})
}

func handleDashboardStatus(c *gin.Context) {
	c.JSON(http.StatusOK, whatsapp.GetManager().GetStatus())
}

func handleDashboardGetConfig(c *gin.Context) {
	c.Header("Cache-Control", "no-store")
	c.JSON(http.StatusOK, config.Get().DashboardSnapshot())
}

func handleDashboardSaveConfig(c *gin.Context) {
	var raw map[string]interface{}
	if err := c.ShouldBindJSON(&raw); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "Format data tidak valid"})
		return
	}

	updates := make(map[string]string)
	for k, v := range raw {
		switch k {
		case "app_name":
			updates["APP_NAME"] = toString(v)
		case "ai_enabled":
			updates["AI_ENABLED"] = toBoolString(v)
		case "ai_base_url":
			updates["AI_BASE_URL"] = toString(v)
		case "ai_api_key":
			// The dashboard never receives an existing key. A blank value means
			// keep the environment supplied secret; a nonblank value rotates it.
			if value := toString(v); value != "" {
				updates["AI_API_KEY"] = value
			}
		case "clear_ai_api_key":
			// Processed after the loop to avoid depending on JSON map iteration order.
		case "ai_model":
			updates["AI_MODEL"] = toString(v)
		case "ai_fallback_message":
			updates["AI_FALLBACK_MESSAGE"] = toString(v)
		case "ai_human_takeover_minutes":
			updates["AI_HUMAN_TAKEOVER_MINUTES"] = toString(v)
		case "ai_typing_delay_enabled":
			updates["AI_TYPING_DELAY_ENABLED"] = toBoolString(v)
		case "ai_typing_delay_value":
			updates["AI_TYPING_DELAY_VALUE"] = toString(v)
		case "ai_typing_delay_unit":
			updates["AI_TYPING_DELAY_UNIT"] = toString(v)
		case "ai_response_template":
			updates["AI_RESPONSE_TEMPLATE"] = toString(v)
		case "otp_expiry_minutes":
			updates["OTP_EXPIRY_MINUTES"] = toString(v)
		case "otp_max_attempts":
			updates["OTP_MAX_ATTEMPTS"] = toString(v)
		case "otp_cooldown_seconds":
			updates["OTP_COOLDOWN_SECONDS"] = toString(v)
		case "otp_template_default":
			updates["OTP_TEMPLATE_DEFAULT"] = toString(v)
		case "otp_template_reset":
			updates["OTP_TEMPLATE_RESET"] = toString(v)
		}
	}
	if clear, ok := raw["clear_ai_api_key"].(bool); ok && clear {
		updates["AI_API_KEY"] = ""
	}
	if _, exists := raw["clear_ai_api_key"]; exists {
		if _, ok := raw["clear_ai_api_key"].(bool); !ok {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "clear_ai_api_key harus berupa boolean"})
			return
		}
	}
	if _, ok := raw["app_name"]; ok {
		name := updates["APP_NAME"]
		if len([]rune(name)) > 80 || strings.TrimSpace(name) == "" {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "nama aplikasi harus 1 sampai 80 karakter"})
			return
		}
	}
	if baseURL, ok := updates["AI_BASE_URL"]; ok && baseURL != "" {
		u, err := url.Parse(baseURL)
		if err != nil || u.Host == "" || u.User != nil || u.Fragment != "" || u.RawQuery != "" || (config.Get().Snapshot().AppEnv == "production" && u.Scheme != "https") || (u.Scheme != "https" && u.Scheme != "http") {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "AI Base URL harus berupa URL HTTP/HTTPS yang valid; production wajib HTTPS"})
			return
		}
	}
	if len(updates["AI_API_KEY"]) > 512 {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "API key terlalu panjang"})
		return
	}
	if value, exists := raw["ai_enabled"]; exists {
		if _, ok := value.(bool); !ok {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "ai_enabled harus berupa boolean"})
			return
		}
	}
	if value, exists := raw["ai_typing_delay_enabled"]; exists {
		if _, ok := value.(bool); !ok {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "ai_typing_delay_enabled harus berupa boolean"})
			return
		}
	}
	if value, exists := raw["ai_enabled"]; exists && value == true {
		current := config.Get().Snapshot()
		key := current.AiApiKey
		if candidate, ok := updates["AI_API_KEY"]; ok {
			key = candidate
		}
		if key == "" {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "AI tidak dapat diaktifkan tanpa API key"})
			return
		}
		model := current.AiModel
		if candidate, ok := updates["AI_MODEL"]; ok {
			model = candidate
		}
		if strings.TrimSpace(model) == "" {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "nama model AI wajib diisi"})
			return
		}
		baseURL := current.AiBaseURL
		if candidate, ok := updates["AI_BASE_URL"]; ok {
			baseURL = candidate
		}
		u, err := url.Parse(baseURL)
		if err != nil || u.Scheme != "https" || u.Host == "" || u.User != nil || u.Fragment != "" || u.RawQuery != "" {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "AI provider memerlukan Base URL HTTPS yang valid"})
			return
		}
	}
	if model := updates["AI_MODEL"]; len(model) > 120 {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "nama model terlalu panjang"})
		return
	}
	for _, key := range []string{"AI_FALLBACK_MESSAGE", "AI_RESPONSE_TEMPLATE", "OTP_TEMPLATE_DEFAULT", "OTP_TEMPLATE_RESET"} {
		if len(updates[key]) > 4000 {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "isi konfigurasi melebihi batas 4000 byte"})
			return
		}
	}
	if _, ok := updates["OTP_EXPIRY_MINUTES"]; ok {
		value, err := strconv.Atoi(updates["OTP_EXPIRY_MINUTES"])
		if err != nil || value < 1 || value > 60 {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "masa berlaku OTP harus 1 sampai 60 menit"})
			return
		}
	}
	if _, ok := updates["OTP_MAX_ATTEMPTS"]; ok {
		value, err := strconv.Atoi(updates["OTP_MAX_ATTEMPTS"])
		if err != nil || value < 1 || value > 10 {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "maksimum percobaan OTP harus 1 sampai 10"})
			return
		}
	}
	if _, ok := updates["OTP_COOLDOWN_SECONDS"]; ok {
		value, err := strconv.Atoi(updates["OTP_COOLDOWN_SECONDS"])
		if err != nil || value < 10 || value > 3600 {
			c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "jeda OTP harus 10 sampai 3600 detik"})
			return
		}
	}
	for key, bounds := range map[string][2]int{"AI_HUMAN_TAKEOVER_MINUTES": {5, 180}, "AI_TYPING_DELAY_VALUE": {0, 10000}} {
		if rawValue, ok := updates[key]; ok {
			value, err := strconv.Atoi(rawValue)
			if err != nil || value < bounds[0] || value > bounds[1] {
				c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "nilai pengaturan AI di luar rentang"})
				return
			}
		}
	}
	if unit, ok := updates["AI_TYPING_DELAY_UNIT"]; ok && unit != "ms" && unit != "s" {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "unit jeda harus ms atau s"})
		return
	}

	if err := config.Get().UpdateAndPersist(updates); err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"success": false, "error": "konfigurasi tidak dapat disimpan"})
		return
	}

	c.JSON(http.StatusOK, gin.H{"success": true, "message": "Konfigurasi berhasil disimpan"})
}

func handleDashboardGetKB(c *gin.Context) {
	c.Header("Cache-Control", "no-store")
	cfg := config.Get().Snapshot()
	kb := ai.GetKnowledgeBase(cfg.AiKnowledgeBasePath)
	c.JSON(http.StatusOK, gin.H{"content": kb.GetContent()})
}

func handleDashboardSaveKB(c *gin.Context) {
	var req KnowledgeBaseRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "Data tidak valid"})
		return
	}
	if len(req.Content) > 32<<10 {
		c.JSON(http.StatusRequestEntityTooLarge, gin.H{"success": false, "error": "basis pengetahuan maksimal 32 KiB"})
		return
	}

	cfg := config.Get().Snapshot()
	kb := ai.GetKnowledgeBase(cfg.AiKnowledgeBasePath)
	if err := kb.UpdateContent(req.Content); err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"success": false, "error": err.Error()})
		return
	}

	c.JSON(http.StatusOK, gin.H{"success": true, "message": "Basis pengetahuan berhasil diperbarui"})
}

func handleDashboardPairingCode(c *gin.Context) {
	var req PairingCodeRequest
	if err := c.ShouldBindJSON(&req); err != nil || req.Phone == "" {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "Nomor WhatsApp diperlukan"})
		return
	}

	code, err := whatsapp.GetManager().RequestPairingCode(req.Phone)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"success": false, "error": err.Error()})
		return
	}

	c.JSON(http.StatusOK, gin.H{"success": true, "code": code})
}

func handleDashboardAiSimulate(c *gin.Context) {
	var req AiSimulateRequest
	if err := c.ShouldBindJSON(&req); err != nil || req.Message == "" {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "Pesan diperlukan"})
		return
	}

	ctx, cancel := context.WithTimeout(context.Background(), 20*time.Second)
	defer cancel()

	if len([]rune(req.Message)) > 1200 {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "pesan simulasi maksimal 1200 karakter"})
		return
	}
	reply, err := ai.GetClient().GenerateReply(ctx, "dashboard-sim", req.Message)
	if err != nil {
		c.JSON(http.StatusBadGateway, gin.H{"success": false, "error": "provider AI tidak dapat memproses permintaan", "reply": reply})
		return
	}

	formattedReply := config.Get().FormatAiResponse(reply)
	c.JSON(http.StatusOK, gin.H{"success": true, "reply": formattedReply})
}

func handleDashboardLogs(c *gin.Context) {
	c.Header("Cache-Control", "no-store")
	logs := audit.Get().GetRecent(50)
	stats := audit.Get().GetStats()
	c.JSON(http.StatusOK, gin.H{
		"logs":  logs,
		"stats": stats,
	})
}

func handleDashboardChangePassword(c *gin.Context) {
	var req ChangePasswordRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "Format tidak valid"})
		return
	}

	if err := dashboard.GetAuth().ChangePassword(req.OldPassword, req.NewPassword); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": err.Error()})
		return
	}
	c.SetSameSite(http.SameSiteStrictMode)
	c.SetCookie("gw_session", "", -1, "/api/dashboard", "", config.Get().Snapshot().AppEnv == "production", true)
	c.JSON(http.StatusOK, gin.H{"success": true, "message": "Kata sandi berhasil diperbarui"})
}

func handleDashboardLogout(c *gin.Context) {
	if token, exists := c.Get("dashboard_token"); exists {
		dashboard.GetAuth().RevokeSession(token.(string))
	}
	c.SetSameSite(http.SameSiteStrictMode)
	c.SetCookie("gw_session", "", -1, "/api/dashboard", "", config.Get().Snapshot().AppEnv == "production", true)
	c.JSON(http.StatusOK, gin.H{"success": true})
}

func toString(v interface{}) string {
	if v == nil {
		return ""
	}
	switch val := v.(type) {
	case string:
		return val
	case float64:
		return strconv.FormatFloat(val, 'f', -1, 64)
	case int:
		return strconv.Itoa(val)
	case bool:
		if val {
			return "true"
		}
		return "false"
	default:
		return ""
	}
}

func toBoolString(v interface{}) string {
	if b, ok := v.(bool); ok {
		if b {
			return "true"
		}
		return "false"
	}
	return toString(v)
}
