package whatsapp

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strings"
	"time"
	"unicode/utf8"

	"wa-gateway/internal/config"
	"wa-gateway/internal/otp"
)

const graphResponseLimit = 1 << 20

// DeliveryOutcomeUnknownError means Meta may have accepted a send even though
// the gateway could not confirm the response.
type DeliveryOutcomeUnknownError struct{}

func (DeliveryOutcomeUnknownError) Error() string {
	return "status pengiriman WhatsApp tidak dapat dipastikan"
}

func DeliveryOutcomeUnknown(err error) bool {
	var target DeliveryOutcomeUnknownError
	return errors.As(err, &target)
}

var graphHTTPClient = &http.Client{
	Timeout:       15 * time.Second,
	CheckRedirect: func(_ *http.Request, _ []*http.Request) error { return http.ErrUseLastResponse },
}

type cloudMessageResponse struct {
	Messages []struct {
		ID string `json:"id"`
	} `json:"messages"`
	Error *struct {
		Message string `json:"message"`
		Code    int    `json:"code"`
	} `json:"error"`
}

func cloudEndpoint(version, object string) string {
	return "https://graph.facebook.com/" + version + "/" + url.PathEscape(object)
}

func (m *Manager) verifyCloudCredentials(ctx context.Context) (*UserInfo, error) {
	cfg := config.Get().Snapshot()
	u, err := url.Parse(cloudEndpoint(cfg.WhatsAppGraphAPIVersion, cfg.WhatsAppPhoneNumberID))
	if err != nil || u.Host != "graph.facebook.com" {
		return nil, fmt.Errorf("Graph API endpoint tidak valid")
	}
	q := u.Query()
	q.Set("fields", "id,display_phone_number,verified_name")
	u.RawQuery = q.Encode()
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, u.String(), nil)
	if err != nil {
		return nil, fmt.Errorf("gagal membuat permintaan verifikasi WhatsApp Cloud API")
	}
	req.Header.Set("Authorization", "Bearer "+cfg.WhatsAppCloudAccessToken)
	resp, err := graphHTTPClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("tidak dapat menjangkau WhatsApp Cloud API: %w", err)
	}
	defer resp.Body.Close()
	body, err := io.ReadAll(io.LimitReader(resp.Body, graphResponseLimit))
	if err != nil {
		return nil, fmt.Errorf("gagal membaca respons verifikasi WhatsApp Cloud API")
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return nil, fmt.Errorf("WhatsApp Cloud API menolak kredensial (HTTP %d)", resp.StatusCode)
	}
	var info struct {
		ID           string `json:"id"`
		Phone        string `json:"display_phone_number"`
		VerifiedName string `json:"verified_name"`
	}
	if err := json.Unmarshal(body, &info); err != nil || info.ID == "" {
		return nil, fmt.Errorf("respons verifikasi WhatsApp Cloud API tidak valid")
	}
	return &UserInfo{ID: info.ID, Phone: info.Phone, Name: info.VerifiedName}, nil
}

func (m *Manager) sendCloudText(to, message string) (string, error) {
	return m.sendCloudPayload(to, map[string]any{
		"messaging_product": "whatsapp",
		"recipient_type":    "individual",
		"to":                to,
		"type":              "text",
		"text": map[string]any{
			"preview_url": false,
			"body":        message,
		},
	})
}

func (m *Manager) sendCloudOTPTemplate(to, templateName, language, code string) (string, error) {
	if strings.TrimSpace(templateName) == "" {
		return "", fmt.Errorf("nama template WhatsApp OTP yang telah disetujui belum dikonfigurasi")
	}
	components, err := otpTemplateComponents(code)
	if err != nil {
		return "", err
	}
	return m.sendCloudPayload(to, map[string]any{
		"messaging_product": "whatsapp",
		"recipient_type":    "individual",
		"to":                to,
		"type":              "template",
		"template": map[string]any{
			"name":       templateName,
			"language":   map[string]string{"code": language},
			"components": components,
		},
	})
}

func otpTemplateComponents(code string) ([]map[string]any, error) {
	if len(code) != 6 {
		return nil, fmt.Errorf("kode OTP tidak valid untuk template WhatsApp")
	}
	for _, digit := range code {
		if digit < '0' || digit > '9' {
			return nil, fmt.Errorf("kode OTP tidak valid untuk template WhatsApp")
		}
	}
	bodyParameter := map[string]string{"type": "text", "text": code}
	buttonParameter := map[string]string{"type": "text", "text": code}
	return []map[string]any{
		{"type": "body", "parameters": []map[string]string{bodyParameter}},
		{"type": "button", "sub_type": "url", "index": "0", "parameters": []map[string]string{buttonParameter}},
	}, nil
}

func (m *Manager) sendCloudPayload(to string, payload map[string]any) (string, error) {
	cfg := config.Get().Snapshot()
	endpoint := cloudEndpoint(cfg.WhatsAppGraphAPIVersion, cfg.WhatsAppPhoneNumberID) + "/messages"
	u, err := url.Parse(endpoint)
	if err != nil || u.Scheme != "https" || u.Host != "graph.facebook.com" {
		return "", fmt.Errorf("Graph API endpoint tidak valid")
	}
	body, err := json.Marshal(payload)
	if err != nil {
		return "", fmt.Errorf("gagal membuat pesan WhatsApp")
	}
	ctx, cancel := context.WithTimeout(context.Background(), 15*time.Second)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, endpoint, bytes.NewReader(body))
	if err != nil {
		return "", fmt.Errorf("gagal membuat permintaan WhatsApp Cloud API")
	}
	req.Header.Set("Authorization", "Bearer "+cfg.WhatsAppCloudAccessToken)
	req.Header.Set("Content-Type", "application/json")
	resp, err := graphHTTPClient.Do(req)
	if err != nil {
		return "", DeliveryOutcomeUnknownError{}
	}
	defer resp.Body.Close()
	responseBody, err := io.ReadAll(io.LimitReader(resp.Body, graphResponseLimit))
	if err != nil {
		return "", DeliveryOutcomeUnknownError{}
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		if resp.StatusCode >= http.StatusInternalServerError {
			return "", DeliveryOutcomeUnknownError{}
		}
		return "", fmt.Errorf("WhatsApp Cloud API menolak pesan (HTTP %d)", resp.StatusCode)
	}
	var decoded cloudMessageResponse
	if err := json.Unmarshal(responseBody, &decoded); err != nil || len(decoded.Messages) == 0 || decoded.Messages[0].ID == "" {
		return "", DeliveryOutcomeUnknownError{}
	}
	return decoded.Messages[0].ID, nil
}

// QueueCloudIncoming durably accepts a verified inbound webhook before the
// HTTP handler acknowledges it. Message content and contact details are erased after processing.
func (m *Manager) QueueCloudIncoming(ctx context.Context, messageID, phone, name, body string) (bool, error) {
	cleanPhone := otp.CleanPhoneNumber(phone)
	if strings.TrimSpace(messageID) == "" || cleanPhone == "" || strings.TrimSpace(body) == "" {
		return false, fmt.Errorf("pesan webhook tidak valid")
	}
	body = strings.TrimSpace(body)
	if utf8.RuneCountInString(body) > 4096 {
		body = string([]rune(body)[:4096])
	}
	name = strings.TrimSpace(name)
	if utf8.RuneCountInString(name) > 120 {
		name = string([]rune(name)[:120])
	}
	m.mu.RLock()
	db := m.db
	m.mu.RUnlock()
	if db == nil {
		return false, fmt.Errorf("database antrean pesan masuk belum siap")
	}
	var pending int
	if err := db.QueryRowContext(ctx, `SELECT COUNT(*) FROM cloud_inbox_messages WHERE status IN ('pending','processing')`).Scan(&pending); err != nil {
		return false, fmt.Errorf("gagal memeriksa kapasitas pesan masuk")
	}
	if pending >= 10000 {
		return false, fmt.Errorf("kapasitas pesan masuk penuh")
	}
	now := time.Now().Unix()
	result, err := db.ExecContext(ctx, `INSERT OR IGNORE INTO cloud_inbox_messages(message_id, phone, contact_name, body, status, created_at, updated_at) VALUES(?, ?, ?, ?, 'pending', ?, ?)`, messageID, cleanPhone, name, body, now, now)
	if err != nil {
		return false, fmt.Errorf("gagal menyimpan pesan masuk")
	}
	rows, err := result.RowsAffected()
	if err != nil {
		return false, fmt.Errorf("gagal membaca hasil penyimpanan pesan masuk")
	}
	return rows == 1, nil
}

func (m *Manager) runCloudInbox(ctx context.Context) {
	defer close(m.inboxDone)
	ticker := time.NewTicker(250 * time.Millisecond)
	defer ticker.Stop()
	var lastCleanup time.Time
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
		}
		if time.Since(lastCleanup) >= time.Hour {
			m.cleanupCloudInbox()
			lastCleanup = time.Now()
		}
		for m.processOneCloudInbox(ctx) {
		}
	}
}

func (m *Manager) processOneCloudInbox(ctx context.Context) bool {
	m.mu.RLock()
	db := m.db
	m.mu.RUnlock()
	if db == nil {
		return false
	}
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return false
	}
	defer tx.Rollback()
	var id, phone, name, body string
	err = tx.QueryRowContext(ctx, `SELECT message_id, phone, contact_name, body FROM cloud_inbox_messages WHERE status='pending' AND next_attempt_at<=? ORDER BY created_at, message_id LIMIT 1`, time.Now().Unix()).Scan(&id, &phone, &name, &body)
	if err != nil {
		return false
	}
	if _, err = tx.ExecContext(ctx, `UPDATE cloud_inbox_messages SET status='processing', updated_at=? WHERE message_id=? AND status='pending'`, time.Now().Unix(), id); err != nil {
		return false
	}
	if err = tx.Commit(); err != nil {
		return false
	}
	m.HandleCloudIncoming(phone, name, body, id)
	return true
}

func (m *Manager) completeCloudInbox(messageIDs []string) {
	if len(messageIDs) == 0 {
		return
	}
	m.mu.RLock()
	db := m.db
	m.mu.RUnlock()
	if db == nil {
		return
	}
	now := time.Now().Unix()
	for _, id := range messageIDs {
		_, _ = db.Exec(`UPDATE cloud_inbox_messages SET status='processed', phone='', contact_name='', body='', updated_at=? WHERE message_id=? AND status='processing'`, now, id)
	}
	m.cleanupCloudInbox()
}

func (m *Manager) cleanupCloudInbox() {
	m.mu.RLock()
	db := m.db
	m.mu.RUnlock()
	if db == nil {
		return
	}
	_, _ = db.Exec(`DELETE FROM cloud_inbox_messages WHERE status='processed' AND updated_at<?`, time.Now().Add(-72*time.Hour).Unix())
}

func (m *Manager) retryCloudInbox(messageIDs []string) {
	if len(messageIDs) == 0 {
		return
	}
	m.mu.RLock()
	db := m.db
	m.mu.RUnlock()
	if db == nil {
		return
	}
	now := time.Now()
	for _, id := range messageIDs {
		_, _ = db.Exec(`UPDATE cloud_inbox_messages SET status='pending', updated_at=?, next_attempt_at=? WHERE message_id=? AND status='processing'`, now.Unix(), now.Add(5*time.Second).Unix(), id)
	}
}
