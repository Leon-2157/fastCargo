package api

import (
	"net/http"
	"strings"
	"time"

	"github.com/gin-gonic/gin"

	"wa-gateway/internal/audit"
	"wa-gateway/internal/config"
	"wa-gateway/internal/otp"
	"wa-gateway/internal/whatsapp"
)

type OtpSendRequest struct {
	PhoneNumber string `json:"phoneNumber"`
	Phone       string `json:"phone"`
	Target      string `json:"target"`
	Purpose     string `json:"purpose"`
	Type        string `json:"type"`
	Template    string `json:"template"`
}

type OtpVerifyRequest struct {
	PhoneNumber string `json:"phoneNumber"`
	Phone       string `json:"phone"`
	Target      string `json:"target"`
	Code        string `json:"code"`
	Otp         string `json:"otp"`
	Purpose     string `json:"purpose"`
}

func handleOtpSend(c *gin.Context) {
	var req OtpSendRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "payload tidak valid"})
		return
	}
	rawPhone := firstNonEmpty(req.PhoneNumber, req.Phone, req.Target)
	cleanPhone := otp.CleanPhoneNumber(rawPhone)
	if cleanPhone == "" {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "nomor WhatsApp tidak valid"})
		return
	}
	if !requestLimiter.allow("otp-phone-send:"+cleanPhone, 5, time.Hour, time.Now()) {
		c.JSON(http.StatusTooManyRequests, gin.H{"success": false, "error": "batas permintaan OTP untuk nomor ini tercapai"})
		return
	}
	purpose := normalizeOTPPurpose(firstNonEmpty(req.Purpose, req.Type))
	if purpose == "" {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "tujuan OTP tidak valid"})
		return
	}
	cfg := config.Get().Snapshot()
	if !whatsapp.GetManager().GetStatus().Paired {
		c.JSON(http.StatusServiceUnavailable, gin.H{"success": false, "error": "layanan WhatsApp belum siap mengirim pesan"})
		return
	}
	code, err := otp.Get().GenerateForPurpose(cleanPhone, purpose, cfg.OtpExpiryMinutes, cfg.OtpCooldownSeconds)
	if err != nil {
		c.JSON(http.StatusTooManyRequests, gin.H{"success": false, "error": "OTP belum dapat dibuat; coba lagi nanti"})
		return
	}
	message := cfg.FormatOtpMessage(code, purpose, "")
	_, err = whatsapp.GetManager().SendOTPMessage(cleanPhone, code, purpose, message, cfg.OtpExpiryMinutes, cfg.AppName)
	if err != nil {
		if !whatsapp.DeliveryOutcomeUnknown(err) {
			otp.Get().DeleteIfMatches(cleanPhone, purpose, code)
		}
		audit.Get().Record(cleanPhone, "otp_send", "", "failed", "delivery failed")
		c.JSON(http.StatusBadGateway, gin.H{"success": false, "error": "gagal mengirim kode melalui WhatsApp"})
		return
	}
	audit.Get().Record(cleanPhone, "otp_send", "", "success", "")
	c.JSON(http.StatusOK, gin.H{"success": true, "message": "Kode OTP berhasil dikirim via WhatsApp", "expiresIn": cfg.OtpExpiryMinutes * 60})
}

func handleOtpVerify(c *gin.Context) {
	var req OtpVerifyRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "payload tidak valid"})
		return
	}
	rawPhone := firstNonEmpty(req.PhoneNumber, req.Phone, req.Target)
	inputCode := strings.TrimSpace(firstNonEmpty(req.Code, req.Otp))
	cleanPhone := otp.CleanPhoneNumber(rawPhone)
	if cleanPhone == "" || !sixDigits(inputCode) {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "nomor atau kode OTP tidak valid"})
		return
	}
	if !requestLimiter.allow("otp-phone-verify:"+cleanPhone, 20, 15*time.Minute, time.Now()) {
		c.JSON(http.StatusTooManyRequests, gin.H{"success": false, "error": "batas verifikasi OTP untuk nomor ini tercapai"})
		return
	}
	purpose := normalizeOTPPurpose(req.Purpose)
	if purpose == "" {
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": "tujuan OTP tidak valid"})
		return
	}
	valid, reason := otp.Get().VerifyForPurpose(cleanPhone, purpose, inputCode, config.Get().Snapshot().OtpMaxAttempts)
	if !valid {
		audit.Get().Record(cleanPhone, "otp_verify", "", "failed", reason)
		c.JSON(http.StatusBadRequest, gin.H{"success": false, "error": reason})
		return
	}
	audit.Get().Record(cleanPhone, "otp_verify", "", "success", "")
	c.JSON(http.StatusOK, gin.H{"success": true, "message": "Nomor WhatsApp berhasil diverifikasi"})
}

func normalizeOTPPurpose(value string) string {
	switch strings.ToLower(strings.TrimSpace(value)) {
	case "", "default", "verify", "verification":
		return "default"
	case "reset", "reset_password":
		return "reset"
	default:
		return ""
	}
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if strings.TrimSpace(value) != "" {
			return strings.TrimSpace(value)
		}
	}
	return ""
}

func sixDigits(value string) bool {
	if len(value) != 6 {
		return false
	}
	for _, r := range value {
		if r < '0' || r > '9' {
			return false
		}
	}
	return true
}
