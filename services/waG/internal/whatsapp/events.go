package whatsapp

import (
	"context"
	"fmt"
	"math/rand"
	"strings"
	"time"

	"go.mau.fi/whatsmeow/proto/waE2E"
	"go.mau.fi/whatsmeow/types"
	"go.mau.fi/whatsmeow/types/events"
	"google.golang.org/protobuf/proto"

	"wa-gateway/internal/ai"
	"wa-gateway/internal/audit"
	"wa-gateway/internal/config"
	"wa-gateway/internal/forwardqueue"
	"wa-gateway/internal/otp"
)

func (m *Manager) handleEvent(rawEvt interface{}) {
	switch evt := rawEvt.(type) {
	case *events.Connected:
		m.mu.Lock()
		m.state = "connected"
		m.currentQrImage = ""
		m.currentQrRaw = ""
		m.currentPairCode = ""

		userID := ""
		userPhone := ""
		if m.client != nil && m.client.Store != nil && m.client.Store.ID != nil {
			userID = m.client.Store.ID.String()
			userPhone = m.client.Store.ID.User
		}
		m.connectedUser = &UserInfo{
			ID:    userID,
			Phone: userPhone,
			Name:  config.Get().Snapshot().AppName + " Official",
		}
		m.mu.Unlock()
		fmt.Printf("[WHATSAPP] Terhubung ke WhatsApp! Akun: %s\n", userPhone)

	case *events.LoggedOut:
		fmt.Println("[WHATSAPP] Sesi dikeluarkan (Logged Out). Menyiapkan QR baru...")
		go func() {
			if err := m.resetSession(false); err != nil {
				fmt.Printf("[WHATSAPP] Gagal reset sesi setelah logout: %v\n", err)
			}
		}()

	case *events.Disconnected:
		m.mu.Lock()
		m.state = "disconnected"
		m.currentQrRaw = ""
		m.currentQrImage = ""
		m.mu.Unlock()
		fmt.Println("[WHATSAPP] Koneksi terputus. Mencoba reconnect...")

	case *events.Message:
		m.handleIncomingMessage(evt)
	}
}

func (m *Manager) handleIncomingMessage(evt *events.Message) {
	// Ignore group chats and broadcast channels
	if evt.Info.IsGroup || evt.Info.Chat.Server == types.BroadcastServer {
		return
	}

	cfg := config.Get().Snapshot()
	senderPhone := otp.CleanPhoneNumber(evt.Info.Chat.User)
	if senderPhone == "" {
		senderPhone = evt.Info.Sender.User
	}

	// 1. Smart Human Takeover: Detect if admin sent a message from their own phone
	if evt.Info.IsFromMe {
		if cfg.AiHumanTakeoverEnable && senderPhone != "" {
			ai.GetTakeover().RecordAdminReply(senderPhone, cfg.AiHumanTakeoverMin)
			fmt.Printf("[SMART TAKEOVER] jeda balasan otomatis diaktifkan\n")
		}
		return
	}

	// 2. Extract message text body
	text := extractMessageText(evt.Message)
	trimmed := strings.TrimSpace(text)
	if trimmed == "" {
		return
	}

	fmt.Printf("[WHATSAPP MASUK] pesan teks diterima\n")
	audit.Get().Record(senderPhone, "incoming_chat", trimmed, "success", "")

	m.queueIncomingText(senderPhone, evt.Info.PushName, trimmed, evt.Info.Chat)
}

// HandleCloudIncoming accepts a verified message from the official Business
// Cloud API webhook and routes it through the same AI/debounce path.
func (m *Manager) HandleCloudIncoming(phone, name, message, messageID string) {
	cleanPhone := otp.CleanPhoneNumber(phone)
	trimmed := strings.TrimSpace(message)
	if cleanPhone == "" || trimmed == "" {
		return
	}
	audit.Get().Record(cleanPhone, "incoming_chat", trimmed, "success", "")
	m.queueIncomingText(cleanPhone, name, trimmed, types.NewJID(cleanPhone, types.DefaultUserServer), messageID)
}

func (m *Manager) queueIncomingText(senderPhone, senderName, trimmed string, chat types.JID, cloudMessageID ...string) {
	// Debounce rapid consecutive messages into one AI request.
	cfg := config.Get().Snapshot()
	debounceSec := cfg.AiDebounceSeconds
	if debounceSec <= 0 {
		debounceSec = 2.5
	}

	m.debounceMu.Lock()
	if m.shuttingDown.Load() {
		m.debounceMu.Unlock()
		return
	}
	entry, exists := m.debounce[senderPhone]
	if exists {
		if entry.timer.Stop() {
			m.dispatchWG.Done()
		}
		entry.messages = append(entry.messages, trimmed)
		if len(cloudMessageID) > 0 && cloudMessageID[0] != "" {
			entry.cloudMessageIDs = append(entry.cloudMessageIDs, cloudMessageID[0])
		}
		m.dispatchWG.Add(1)
		entry.timer = time.AfterFunc(time.Duration(debounceSec*float64(time.Second)), func() {
			defer m.dispatchWG.Done()
			m.dispatchDebouncedMessage(senderPhone, senderName, chat)
		})
	} else {
		newEntry := &debounceEntry{
			messages: []string{trimmed},
			sender:   chat,
		}
		if len(cloudMessageID) > 0 && cloudMessageID[0] != "" {
			newEntry.cloudMessageIDs = append(newEntry.cloudMessageIDs, cloudMessageID[0])
		}
		m.dispatchWG.Add(1)
		newEntry.timer = time.AfterFunc(time.Duration(debounceSec*float64(time.Second)), func() {
			defer m.dispatchWG.Done()
			m.dispatchDebouncedMessage(senderPhone, senderName, chat)
		})
		m.debounce[senderPhone] = newEntry
	}
	m.debounceMu.Unlock()
}

func (m *Manager) dispatchDebouncedMessage(senderPhone, senderName string, chatJid types.JID) {
	m.debounceMu.Lock()
	entry, found := m.debounce[senderPhone]
	if !found {
		m.debounceMu.Unlock()
		return
	}
	delete(m.debounce, senderPhone)
	m.debounceMu.Unlock()
	if m.shuttingDown.Load() {
		return
	}
	completeInbox := true
	defer func() {
		if completeInbox {
			m.completeCloudInbox(entry.cloudMessageIDs)
		} else {
			m.retryCloudInbox(entry.cloudMessageIDs)
		}
	}()

	combinedText := strings.Join(entry.messages, "\n")
	cfg := config.Get().Snapshot()

	// Persist the forward before acknowledging the inbound Cloud API event.
	if err := m.forwardToWebhook(senderPhone, senderName, combinedText); err != nil {
		completeInbox = false
		fmt.Printf("[WEBHOOK] antrean belum dapat menerima pesan; pesan masuk dipertahankan untuk pemulihan\n")
	}

	// Check Smart Human Takeover
	if cfg.AiHumanTakeoverEnable && ai.GetTakeover().IsPaused(senderPhone) {
		fmt.Printf("[AI AUTO-REPLY] balasan otomatis dilewati selama human takeover\n")
		return
	}

	if !cfg.AiEnabled {
		return
	}
	if m.aiSlots != nil {
		select {
		case m.aiSlots <- struct{}{}:
			defer func() { <-m.aiSlots }()
		case <-time.After(5 * time.Second):
			fmt.Printf("[AI] kapasitas pemrosesan penuh; pesan tidak dikirim ke provider\n")
			audit.Get().Record(senderPhone, "ai_reply", "", "failed", "AI concurrency limit")
			if cfg.AiFallbackMessage != "" {
				if cfg.WhatsAppMode == "cloud" {
					_, _ = m.sendCloudText(senderPhone, cfg.AiFallbackMessage)
				} else {
					m.mu.RLock()
					fallbackClient := m.client
					m.mu.RUnlock()
					if fallbackClient != nil && fallbackClient.IsConnected() {
						_, _ = fallbackClient.SendMessage(context.Background(), chatJid, &waE2E.Message{Conversation: proto.String(cfg.AiFallbackMessage)})
					}
				}
			}
			return
		}
	}

	// Send presence "composing"
	m.mu.RLock()
	cli := m.client
	m.mu.RUnlock()

	if cli != nil && cli.IsConnected() {
		_ = cli.SendChatPresence(context.Background(), chatJid, types.ChatPresenceComposing, types.ChatPresenceMediaText)
	}

	// Call AI client
	ctx, cancel := context.WithTimeout(context.Background(), 25*time.Second)
	defer cancel()

	reply, err := ai.GetClient().GenerateReply(ctx, senderPhone, combinedText)
	if err != nil {
		fmt.Printf("[AI ERROR] provider gagal menghasilkan balasan\n")
		audit.Get().Record(senderPhone, "ai_reply", cfg.AiFallbackMessage, "failed", err.Error())
		if cfg.AiFallbackMessage != "" {
			if cfg.WhatsAppMode == "cloud" {
				_, _ = m.sendCloudText(senderPhone, cfg.AiFallbackMessage)
			} else if cli != nil && cli.IsConnected() {
				_, _ = cli.SendMessage(context.Background(), chatJid, &waE2E.Message{
					Conversation: proto.String(cfg.AiFallbackMessage),
				})
			}
		}
		return
	}

	// Anti-ban human-like randomized typing delay
	if cfg.AiTypingDelayEnabled && cfg.AiTypingDelayValue > 0 {
		baseMs := cfg.AiTypingDelayValue
		if cfg.AiTypingDelayUnit == "s" {
			baseMs *= 1000
		}
		// Apply +/- 25% random jitter for natural human variation
		jitterRange := int(float64(baseMs) * 0.5)
		if jitterRange > 0 {
			baseMs = baseMs - (jitterRange / 2) + rand.Intn(jitterRange)
		}
		if baseMs < 100 {
			baseMs = 100
		}
		time.Sleep(time.Duration(baseMs) * time.Millisecond)
	} else if !cfg.AiTypingDelayEnabled {
		time.Sleep(200 * time.Millisecond)
	}

	formattedReply := cfg.FormatAiResponse(reply)

	// Send AI reply
	if cfg.WhatsAppMode == "cloud" {
		_, sendErr := m.sendCloudText(senderPhone, formattedReply)
		if sendErr != nil {
			fmt.Printf("[AI SEND ERROR] pengiriman balasan gagal\n")
			audit.Get().Record(senderPhone, "ai_reply", formattedReply, "failed", sendErr.Error())
		} else {
			audit.Get().Record(senderPhone, "ai_reply", formattedReply, "success", "")
		}
	} else if cli != nil && cli.IsConnected() {
		_, sendErr := cli.SendMessage(context.Background(), chatJid, &waE2E.Message{
			Conversation: proto.String(formattedReply),
		})
		if sendErr != nil {
			fmt.Printf("[AI SEND ERROR] pengiriman balasan gagal\n")
			audit.Get().Record(senderPhone, "ai_reply", formattedReply, "failed", sendErr.Error())
		} else {
			fmt.Printf("[AI AUTO-REPLY] balasan otomatis terkirim\n")
			audit.Get().Record(senderPhone, "ai_reply", formattedReply, "success", "")
		}
	}
}

// Forward incoming message to external webhook (e.g. Laravel)
func (m *Manager) forwardToWebhook(senderPhone, senderName, message string) error {
	cfg := config.Get().Snapshot()
	webhookURL := cfg.WebhookURL
	if webhookURL == "" {
		return nil
	}
	_, err := forwardqueue.Get().Enqueue(webhookURL, senderPhone, senderName, message)
	return err
}

func extractMessageText(msg *waE2E.Message) string {
	if msg == nil {
		return ""
	}
	if msg.Conversation != nil && *msg.Conversation != "" {
		return *msg.Conversation
	}
	if msg.ExtendedTextMessage != nil && msg.ExtendedTextMessage.Text != nil {
		return *msg.ExtendedTextMessage.Text
	}
	if msg.ImageMessage != nil && msg.ImageMessage.Caption != nil {
		return *msg.ImageMessage.Caption
	}
	if msg.VideoMessage != nil && msg.VideoMessage.Caption != nil {
		return *msg.VideoMessage.Caption
	}
	if msg.DocumentMessage != nil && msg.DocumentMessage.Caption != nil {
		return *msg.DocumentMessage.Caption
	}
	return ""
}
