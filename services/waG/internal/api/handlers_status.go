package api

import (
	"net/http"
	"time"

	"github.com/gin-gonic/gin"

	"wa-gateway/internal/config"
	"wa-gateway/internal/whatsapp"
)

var appStartTime = time.Now()

func handleHealth(c *gin.Context) {
	c.Header("Cache-Control", "no-store")
	c.JSON(http.StatusOK, gin.H{
		"status":    "ok",
		"service":   config.Get().Snapshot().AppName + " WhatsApp Gateway",
		"uptime":    int64(time.Since(appStartTime).Seconds()),
		"timestamp": time.Now().Format(time.RFC3339),
	})
}

func handleStatus(c *gin.Context) {
	status := whatsapp.GetManager().GetStatus()
	c.Header("Cache-Control", "no-store")
	// Public status contains no phone number, JID, QR, pairing code, or secrets.
	c.JSON(http.StatusOK, gin.H{
		"service":   status.Service,
		"status":    status.Status,
		"ready":     status.Paired,
		"uptime":    status.UptimeSeconds,
		"timestamp": status.Timestamp,
	})
}

func handleReadiness(c *gin.Context) {
	status := whatsapp.GetManager().GetStatus()
	c.Header("Cache-Control", "no-store")
	if !status.Paired {
		c.JSON(http.StatusServiceUnavailable, gin.H{"ready": false, "status": status.Status})
		return
	}
	c.JSON(http.StatusOK, gin.H{"ready": true})
}

func handleInternalStatus(c *gin.Context) {
	status := whatsapp.GetManager().GetStatus()
	qr := whatsapp.GetManager().GetInternalQR()
	c.Header("Cache-Control", "no-store")
	c.JSON(http.StatusOK, gin.H{
		"service":      status.Service,
		"status":       status.Status,
		"paired":       status.Paired,
		"ready":        status.Paired,
		"qr_available": qr.Raw != "" || qr.Image != "",
		"pairing_code": status.PairingCode,
		"user":         status.User,
		"uptime":       status.UptimeSeconds,
		"sandbox":      status.Sandbox,
		"timestamp":    status.Timestamp,
	})
}

func handleInternalQr(c *gin.Context) {
	qr := whatsapp.GetManager().GetInternalQR()
	c.Header("Cache-Control", "no-store")
	if qr.Paired {
		c.JSON(http.StatusNotFound, gin.H{
			"status": false,
			"paired": true,
			"state":  qr.Status,
			"error":  "WhatsApp sudah terhubung",
		})
		return
	}
	payload := qr.Raw
	if payload == "" {
		payload = qr.Image
	}
	if payload == "" {
		c.JSON(http.StatusNotFound, gin.H{
			"status": false,
			"paired": false,
			"state":  qr.Status,
			"error":  "QR code belum tersedia",
		})
		return
	}
	c.JSON(http.StatusOK, gin.H{
		"status":   true,
		"qr":       payload,
		"qr_image": qr.Image,
		"state":    qr.Status,
		"paired":   qr.Paired,
	})
}
