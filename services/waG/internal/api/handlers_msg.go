package api

import (
	"net/http"
	"strings"
	"unicode/utf8"

	"github.com/gin-gonic/gin"

	"wa-gateway/internal/otp"
	"wa-gateway/internal/queue"
	"wa-gateway/internal/whatsapp"
)

type SendMessageRequest struct {
	Target  string `json:"target"`
	Phone   string `json:"phone"`
	Message string `json:"message"`
	Async   bool   `json:"async"`
}

func handleSendMessage(c *gin.Context) {
	var req SendMessageRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": "Invalid JSON format"})
		return
	}

	target := req.Target
	if target == "" {
		target = req.Phone
	}

	cleanTarget, _, err := whatsapp.ResolveTargetJID(target)
	if err != nil || strings.TrimSpace(req.Message) == "" || utf8.RuneCountInString(req.Message) > 4096 {
		c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": "Nomor target dan isi pesan wajib diisi"})
		return
	}

	if req.Async {
		q := queue.Get()
		if q == nil {
			c.JSON(http.StatusServiceUnavailable, gin.H{"status": false, "error": "antrean pesan belum siap"})
			return
		}
		jobID, err := q.EnqueueAsync(cleanTarget, req.Message)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"status": false, "error": err.Error()})
			return
		}
		statusURL := "/send/" + jobID
		if strings.HasPrefix(c.FullPath(), "/api/dashboard/") {
			statusURL = "/api/dashboard/messages/" + jobID
		}
		c.JSON(http.StatusAccepted, gin.H{
			"status":     true,
			"queued":     true,
			"job_id":     jobID,
			"status_url": statusURL,
			"message":    "Pesan diterima antrean; periksa status pekerjaan untuk hasil pengiriman",
		})
		return
	}

	msgID, err := whatsapp.GetManager().SendMessage(cleanTarget, req.Message)
	if err != nil {
		c.JSON(http.StatusBadGateway, gin.H{"status": false, "error": "pengiriman pesan gagal"})
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"status":    true,
		"messageId": msgID,
		"target":    target,
	})
}

func handleSendJobStatus(c *gin.Context) {
	q := queue.Get()
	if q == nil {
		c.JSON(http.StatusServiceUnavailable, gin.H{"status": false, "error": "antrean pesan belum siap"})
		return
	}
	status, err := q.GetStatus(c.Param("id"))
	if err != nil {
		c.JSON(http.StatusNotFound, gin.H{"status": false, "error": "pekerjaan tidak ditemukan"})
		return
	}
	c.Header("Cache-Control", "no-store")
	c.JSON(http.StatusOK, status)
}

func handleLogout(c *gin.Context) {
	if err := whatsapp.GetManager().Logout(); err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"status": false, "error": err.Error()})
		return
	}
	c.JSON(http.StatusOK, gin.H{"status": true, "message": "Sesi WhatsApp telah dihapus dan sedang merestart..."})
}

func handleRestart(c *gin.Context) {
	if err := whatsapp.GetManager().Restart(); err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"status": false, "error": err.Error()})
		return
	}
	c.JSON(http.StatusOK, gin.H{"status": true, "message": "Menghubungkan ulang..."})
}
