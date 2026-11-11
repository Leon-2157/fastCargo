package api

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"io"
	"net/http"
	"strings"
	"time"

	"github.com/gin-gonic/gin"

	"wa-gateway/internal/config"
	"wa-gateway/internal/queue"
	"wa-gateway/internal/whatsapp"
)

const maxWhatsAppWebhookBody = 1 << 20

func handleWhatsAppWebhookVerify(c *gin.Context) {
	query := c.Request.URL.Query()
	if query.Get("hub.mode") != "subscribe" || !constantStringEqual(query.Get("hub.verify_token"), config.Get().Snapshot().WhatsAppWebhookVerifyToken) || config.Get().Snapshot().WhatsAppWebhookVerifyToken == "" {
		c.AbortWithStatus(http.StatusForbidden)
		return
	}
	c.Data(http.StatusOK, "text/plain; charset=utf-8", []byte(query.Get("hub.challenge")))
}

func handleWhatsAppWebhook(c *gin.Context) {
	cfg := config.Get().Snapshot()
	if cfg.WhatsAppMode != "cloud" || cfg.WhatsAppAppSecret == "" {
		c.AbortWithStatus(http.StatusNotFound)
		return
	}
	c.Request.Body = http.MaxBytesReader(c.Writer, c.Request.Body, maxWhatsAppWebhookBody)
	body, err := io.ReadAll(c.Request.Body)
	if err != nil {
		c.AbortWithStatusJSON(http.StatusRequestEntityTooLarge, gin.H{"error": "payload webhook terlalu besar atau tidak terbaca"})
		return
	}
	if !validWebhookSignature(cfg.WhatsAppAppSecret, c.GetHeader("X-Hub-Signature-256"), body) {
		c.AbortWithStatus(http.StatusUnauthorized)
		return
	}
	var event cloudWebhookEnvelope
	if err := json.Unmarshal(body, &event); err != nil {
		c.AbortWithStatusJSON(http.StatusBadRequest, gin.H{"error": "payload webhook tidak valid"})
		return
	}

	manager := whatsapp.GetManager()
	for _, entry := range event.Entry {
		for _, change := range entry.Changes {
			value := change.Value
			for _, status := range value.Statuses {
				if status.ID == "" || !supportedDeliveryStatus(status.Status) {
					continue
				}
				claimCtx, cancel := context.WithTimeout(c.Request.Context(), 3*time.Second)
				statusErr := queue.UpdateDeliveryStatus(claimCtx, status.ID, status.Status)
				cancel()
				if statusErr != nil {
					c.AbortWithStatusJSON(http.StatusServiceUnavailable, gin.H{"error": "status pengiriman belum dapat disimpan"})
					return
				}
			}
			contactNames := make(map[string]string, len(value.Contacts))
			for _, contact := range value.Contacts {
				contactNames[contact.WAID] = contact.Profile.Name
			}
			for _, msg := range value.Messages {
				if msg.ID == "" || msg.From == "" {
					continue
				}
				text := msg.textBody()
				if strings.TrimSpace(text) == "" {
					continue
				}
				claimCtx, cancel := context.WithTimeout(c.Request.Context(), 3*time.Second)
				_, claimErr := manager.QueueCloudIncoming(claimCtx, msg.ID, msg.From, contactNames[msg.From], text)
				cancel()
				if claimErr != nil {
					c.AbortWithStatusJSON(http.StatusServiceUnavailable, gin.H{"error": "pesan webhook belum dapat disimpan"})
					return
				}
			}
		}
	}
	c.Status(http.StatusOK)
}

func supportedDeliveryStatus(status string) bool {
	switch status {
	case "sent", "delivered", "read", "failed":
		return true
	default:
		return false
	}
}

func validWebhookSignature(secret, header string, body []byte) bool {
	if secret == "" || !strings.HasPrefix(header, "sha256=") {
		return false
	}
	provided, err := hex.DecodeString(strings.TrimPrefix(header, "sha256="))
	if err != nil || len(provided) != sha256.Size {
		return false
	}
	mac := hmac.New(sha256.New, []byte(secret))
	_, _ = mac.Write(body)
	return hmac.Equal(provided, mac.Sum(nil))
}

func constantStringEqual(a, b string) bool {
	return hmac.Equal([]byte(a), []byte(b))
}

type cloudWebhookEnvelope struct {
	Entry []struct {
		Changes []struct {
			Value struct {
				Contacts []struct {
					WAID    string `json:"wa_id"`
					Profile struct {
						Name string `json:"name"`
					} `json:"profile"`
				} `json:"contacts"`
				Messages []cloudWebhookMessage `json:"messages"`
				Statuses []cloudWebhookStatus  `json:"statuses"`
			} `json:"value"`
		} `json:"changes"`
	} `json:"entry"`
}

type cloudWebhookStatus struct {
	ID     string `json:"id"`
	Status string `json:"status"`
}

type cloudWebhookMessage struct {
	ID   string `json:"id"`
	From string `json:"from"`
	Type string `json:"type"`
	Text struct {
		Body string `json:"body"`
	} `json:"text"`
	Image struct {
		Caption string `json:"caption"`
	} `json:"image"`
	Video struct {
		Caption string `json:"caption"`
	} `json:"video"`
	Document struct {
		Caption string `json:"caption"`
	} `json:"document"`
	Interactive struct {
		ButtonReply struct {
			Title string `json:"title"`
		} `json:"button_reply"`
		ListReply struct {
			Title string `json:"title"`
		} `json:"list_reply"`
	} `json:"interactive"`
}

func (m cloudWebhookMessage) textBody() string {
	switch m.Type {
	case "text":
		return m.Text.Body
	case "image":
		return m.Image.Caption
	case "video":
		return m.Video.Caption
	case "document":
		return m.Document.Caption
	case "interactive":
		if m.Interactive.ButtonReply.Title != "" {
			return m.Interactive.ButtonReply.Title
		}
		return m.Interactive.ListReply.Title
	default:
		return ""
	}
}
