package audit

import (
	"strings"
	"sync"
	"time"
)

type LogEntry struct {
	ID        int64     `json:"id"`
	Timestamp time.Time `json:"timestamp"`
	Phone     string    `json:"phone"`
	Type      string    `json:"type"`    // "otp_send", "otp_verify", "ai_reply", "manual_send", "incoming_chat"
	Snippet   string    `json:"snippet"` // Truncated preview of message
	Status    string    `json:"status"`  // "success", "failed", "pending"
	Error     string    `json:"error,omitempty"`
}

type Stats struct {
	TotalOtpSent     int64 `json:"total_otp_sent"`
	TotalOtpVerified int64 `json:"total_otp_verified"`
	TotalAiReplies   int64 `json:"total_ai_replies"`
	TotalIncoming    int64 `json:"total_incoming"`
	TotalManual      int64 `json:"total_manual"`
	TotalErrors      int64 `json:"total_errors"`
}

type Logger struct {
	mu      sync.RWMutex
	entries []LogEntry
	nextID  int64
	limit   int
	stats   Stats
}

var (
	defaultLogger *Logger
	once          sync.Once
)

func Get() *Logger {
	once.Do(func() {
		defaultLogger = &Logger{
			entries: make([]LogEntry, 0, 100),
			limit:   100,
			nextID:  1,
		}
	})
	return defaultLogger
}

func (l *Logger) Record(phone, logType, snippet, status, errMsg string) {
	l.mu.Lock()
	defer l.mu.Unlock()

	// Update stats
	switch logType {
	case "otp_send":
		l.stats.TotalOtpSent++
	case "otp_verify":
		if status == "success" {
			l.stats.TotalOtpVerified++
		}
	case "ai_reply":
		l.stats.TotalAiReplies++
	case "incoming_chat":
		l.stats.TotalIncoming++
	case "manual_send":
		l.stats.TotalManual++
	}

	if status == "failed" {
		l.stats.TotalErrors++
	}

	// Activity history is visible in the admin dashboard. Store event labels,
	// not chat text, OTP values, provider payloads, or full phone numbers.
	switch logType {
	case "otp_send":
		snippet = "Permintaan OTP"
	case "otp_verify":
		snippet = "Verifikasi OTP"
	case "ai_reply":
		snippet = "Pemrosesan balasan AI"
	case "incoming_chat":
		snippet = "Pesan masuk diproses"
	case "manual_send":
		snippet = "Pengiriman pesan"
	default:
		snippet = "Aktivitas layanan"
	}
	phone = maskPhone(phone)
	if len(errMsg) > 160 {
		errMsg = errMsg[:160]
	}
	errMsg = strings.ReplaceAll(errMsg, "\n", " ")

	entry := LogEntry{
		ID:        l.nextID,
		Timestamp: time.Now(),
		Phone:     phone,
		Type:      logType,
		Snippet:   snippet,
		Status:    status,
		Error:     errMsg,
	}
	l.nextID++

	// Keep newest at front
	l.entries = append([]LogEntry{entry}, l.entries...)
	if len(l.entries) > l.limit {
		l.entries = l.entries[:l.limit]
	}
}

func maskPhone(phone string) string {
	digits := make([]rune, 0, len(phone))
	for _, r := range phone {
		if r >= '0' && r <= '9' {
			digits = append(digits, r)
		}
	}
	if len(digits) <= 4 {
		return "••••"
	}
	return strings.Repeat("•", len(digits)-4) + string(digits[len(digits)-4:])
}

func (l *Logger) GetRecent(count int) []LogEntry {
	l.mu.RLock()
	defer l.mu.RUnlock()

	if count <= 0 || count > len(l.entries) {
		count = len(l.entries)
	}

	res := make([]LogEntry, count)
	copy(res, l.entries[:count])
	return res
}

func (l *Logger) GetStats() Stats {
	l.mu.RLock()
	defer l.mu.RUnlock()
	return l.stats
}
