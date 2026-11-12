package whatsapp

import (
	"context"
	"database/sql"
	"encoding/base64"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"sync/atomic"
	"time"
	"unicode/utf8"

	"github.com/skip2/go-qrcode"
	"go.mau.fi/whatsmeow"
	"go.mau.fi/whatsmeow/proto/waE2E"
	"go.mau.fi/whatsmeow/store"
	"go.mau.fi/whatsmeow/store/sqlstore"
	"go.mau.fi/whatsmeow/types"
	waLog "go.mau.fi/whatsmeow/util/log"
	"google.golang.org/protobuf/proto"
	_ "modernc.org/sqlite"

	"wa-gateway/internal/audit"
	"wa-gateway/internal/config"
	"wa-gateway/internal/otp"
)

type UserInfo struct {
	ID    string `json:"id"`
	Phone string `json:"phone"`
	Name  string `json:"name"`
}

type GatewayStatus struct {
	Service       string    `json:"service"`
	Status        string    `json:"status"` // "disconnected" | "connecting" | "qr_ready" | "connected"
	Paired        bool      `json:"paired"`
	QrImage       string    `json:"qr,omitempty"`
	PairingCode   string    `json:"pairing_code,omitempty"`
	User          *UserInfo `json:"user,omitempty"`
	UptimeSeconds int64     `json:"uptime"`
	Sandbox       bool      `json:"sandbox"`
	Timestamp     string    `json:"timestamp"`
}

type Manager struct {
	mu              sync.RWMutex
	connectMu       sync.Mutex
	client          *whatsmeow.Client
	container       *sqlstore.Container
	startTime       time.Time
	state           string // "disconnected" | "connecting" | "qr_ready" | "connected"
	currentQrRaw    string
	currentQrImage  string
	currentPairCode string
	connectedUser   *UserInfo
	db              *sql.DB
	inboxCancel     context.CancelFunc
	inboxDone       chan struct{}
	dispatchWG      sync.WaitGroup
	shuttingDown    atomic.Bool
	aiSlots         chan struct{}

	// Debounce buffer for incoming messages per sender phone
	debounceMu sync.Mutex
	debounce   map[string]*debounceEntry
}

type debounceEntry struct {
	messages        []string
	cloudMessageIDs []string
	sender          types.JID
	timer           *time.Timer
}

var (
	defaultManager *Manager
	once           sync.Once
)

func GetManager() *Manager {
	once.Do(func() {
		defaultManager = &Manager{
			startTime: time.Now(),
			state:     "disconnected",
			debounce:  make(map[string]*debounceEntry),
			aiSlots:   make(chan struct{}, 8),
		}
	})
	return defaultManager
}

// Init initializes the SQLite store and whatsmeow client
func (m *Manager) Init() error {
	m.mu.Lock()
	defer m.mu.Unlock()
	if m.startTime.IsZero() {
		m.startTime = time.Now()
	}
	if m.debounce == nil {
		m.debounce = make(map[string]*debounceEntry)
	}
	if m.aiSlots == nil {
		m.aiSlots = make(chan struct{}, 8)
	}

	cfg := config.Get().Snapshot()
	if cfg.WhatsAppMode == "cloud" {
		dir := filepath.Dir(cfg.SessionDbPath)
		if dir != "" && dir != "." {
			if err := os.MkdirAll(dir, 0700); err != nil {
				return fmt.Errorf("gagal membuat direktori storage: %w", err)
			}
			if err := os.Chmod(dir, 0700); err != nil {
				return fmt.Errorf("izin direktori storage tidak aman")
			}
		}
		dsn := fmt.Sprintf("file:%s?_pragma=journal_mode(WAL)&_pragma=busy_timeout(5000)&_pragma=synchronous(FULL)", cfg.SessionDbPath)
		db, err := sql.Open("sqlite", dsn)
		if err != nil {
			return fmt.Errorf("gagal membuka database storage: %w", err)
		}
		db.SetMaxOpenConns(1)
		if err := db.Ping(); err != nil {
			_ = db.Close()
			return fmt.Errorf("gagal mengakses database storage: %w", err)
		}
		if err := os.Chmod(cfg.SessionDbPath, 0600); err != nil {
			_ = db.Close()
			return fmt.Errorf("izin database storage tidak aman")
		}
		if _, err := db.Exec(`CREATE TABLE IF NOT EXISTS cloud_inbox_messages (
			message_id TEXT PRIMARY KEY,
			phone TEXT NOT NULL,
			contact_name TEXT NOT NULL DEFAULT '',
			body TEXT NOT NULL,
			status TEXT NOT NULL,
			created_at INTEGER NOT NULL,
			updated_at INTEGER NOT NULL,
			next_attempt_at INTEGER NOT NULL DEFAULT 0
		)`); err != nil {
			_ = db.Close()
			return fmt.Errorf("gagal menyiapkan antrean pesan masuk: %w", err)
		}
		if err := ensureInboxRetryColumn(db); err != nil {
			_ = db.Close()
			return fmt.Errorf("gagal memutakhirkan skema antrean pesan masuk")
		}
		if _, err := db.Exec(`CREATE INDEX IF NOT EXISTS idx_cloud_inbox_status ON cloud_inbox_messages(status, created_at)`); err != nil {
			_ = db.Close()
			return fmt.Errorf("gagal menyiapkan indeks pesan masuk: %w", err)
		}
		now := time.Now().Unix()
		_, _ = db.Exec(`UPDATE cloud_inbox_messages SET status='pending', updated_at=?, next_attempt_at=? WHERE status='processing'`, now, now)
		m.db = db
		m.state = "disconnected"
		ctx, cancel := context.WithCancel(context.Background())
		m.inboxCancel = cancel
		m.inboxDone = make(chan struct{})
		go m.runCloudInbox(ctx)
		return nil
	}
	if cfg.AppEnv == "production" {
		return fmt.Errorf("klien WhatsApp Web tidak boleh dijalankan pada production")
	}
	if cfg.WhatsAppMode != "whatsmeow" && cfg.WhatsAppMode != "mock" {
		return fmt.Errorf("WHATSAPP_MODE tidak dikenal")
	}
	if cfg.WhatsAppMode == "mock" {
		m.state = "disconnected"
		return nil
	}
	dir := filepath.Dir(cfg.SessionDbPath)
	if dir != "" && dir != "." {
		if err := os.MkdirAll(dir, 0700); err != nil {
			return fmt.Errorf("gagal membuat direktori storage")
		}
		_ = os.Chmod(dir, 0700)
	}

	// Gunakan WAL mode, busy_timeout 5000ms, dan foreign_keys agar WhatsApp history sync tidak bentrok lock
	dsn := fmt.Sprintf("file:%s?_pragma=foreign_keys(1)&_pragma=journal_mode(WAL)&_pragma=busy_timeout(5000)&_pragma=synchronous(NORMAL)", cfg.SessionDbPath)
	db, err := sql.Open("sqlite", dsn)
	if err != nil {
		return fmt.Errorf("gagal membuka database SQLite: %w", err)
	}
	db.SetMaxOpenConns(1)
	if err := db.Ping(); err != nil {
		_ = db.Close()
		return fmt.Errorf("gagal mengakses database SQLite: %w", err)
	}
	m.db = db
	if err := os.Chmod(cfg.SessionDbPath, 0600); err != nil {
		_ = db.Close()
		return fmt.Errorf("izin database storage tidak aman")
	}

	logger := waLog.Stdout("WhatsApp", "WARN", true)
	container := sqlstore.NewWithDB(db, "sqlite3", logger)
	if err := container.Upgrade(context.Background()); err != nil {
		return fmt.Errorf("gagal mengupgrade skema database: %w", err)
	}
	m.container = container

	return m.resetClientLocked()
}

// resetClientLocked binds a fresh non-deleted device store and whatsmeow client.
func (m *Manager) resetClientLocked() error {
	if m.container == nil {
		return fmt.Errorf("container whatsmeow belum diinisialisasi")
	}

	deviceStore, err := m.container.GetFirstDevice(context.Background())
	if err != nil {
		return fmt.Errorf("gagal mengambil data device store: %w", err)
	}
	if deviceStore.Deleted {
		deviceStore = m.container.NewDevice()
	}

	logger := waLog.Stdout("WhatsApp", "WARN", true)
	cli := whatsmeow.NewClient(deviceStore, logger)
	cli.AddEventHandler(m.handleEvent)
	m.client = cli

	return nil
}

func ensureInboxRetryColumn(db *sql.DB) error {
	rows, err := db.Query(`PRAGMA table_info(cloud_inbox_messages)`)
	if err != nil {
		return err
	}
	found := false
	for rows.Next() {
		var cid, notNull, primaryKey int
		var name, dataType string
		var defaultValue sql.NullString
		if err := rows.Scan(&cid, &name, &dataType, &notNull, &defaultValue, &primaryKey); err != nil {
			_ = rows.Close()
			return err
		}
		if name == "next_attempt_at" {
			found = true
		}
	}
	if err := rows.Err(); err != nil {
		_ = rows.Close()
		return err
	}
	if err := rows.Close(); err != nil {
		return err
	}
	if !found {
		_, err = db.Exec(`ALTER TABLE cloud_inbox_messages ADD COLUMN next_attempt_at INTEGER NOT NULL DEFAULT 0`)
	}
	return err
}

// StartConnect initiates connection to WhatsApp
func (m *Manager) StartConnect() error {
	if config.Get().Snapshot().WhatsAppMode == "cloud" {
		m.mu.Lock()
		m.state = "connecting"
		m.mu.Unlock()
		ctx, cancel := context.WithTimeout(context.Background(), 15*time.Second)
		defer cancel()
		user, err := m.verifyCloudCredentials(ctx)
		m.mu.Lock()
		defer m.mu.Unlock()
		if err != nil {
			m.state = "disconnected"
			return err
		}
		m.connectedUser = user
		m.state = "connected"
		return nil
	}
	if config.Get().Snapshot().WhatsAppMode == "mock" {
		m.mu.Lock()
		m.state = "disconnected"
		m.mu.Unlock()
		return nil
	}
	m.connectMu.Lock()
	defer m.connectMu.Unlock()

	m.mu.Lock()
	if m.client == nil {
		m.mu.Unlock()
		return fmt.Errorf("client whatsmeow belum diinisialisasi")
	}

	if m.client.Store != nil && m.client.Store.Deleted {
		if err := m.resetClientLocked(); err != nil {
			m.state = "disconnected"
			m.mu.Unlock()
			return err
		}
	}

	if m.client.IsConnected() {
		m.mu.Unlock()
		return nil
	}

	m.state = "connecting"
	m.currentQrRaw = ""
	m.currentQrImage = ""
	cli := m.client
	m.mu.Unlock()

	if cli.Store.ID == nil {
		// Device not paired yet: listen to QR channel
		qrChan, err := cli.GetQRChannel(context.Background())
		if err != nil {
			m.mu.Lock()
			if m.client == cli && m.state == "connecting" {
				m.state = "disconnected"
			}
			m.mu.Unlock()
			return err
		}
		go func(boundCli *whatsmeow.Client) {
			for evt := range qrChan {
				if evt.Event == "code" {
					m.mu.RLock()
					sameClient := m.client == boundCli
					m.mu.RUnlock()
					if sameClient {
						m.setQR(evt.Code)
					}
					continue
				}

				fmt.Printf("[WHATSAPP] QR Event: %s\n", evt.Event)
				m.mu.Lock()
				if m.client == boundCli && m.state != "connected" {
					m.currentQrRaw = ""
					m.currentQrImage = ""
					if evt.Event == "success" {
						m.state = "connecting"
					} else {
						m.state = "disconnected"
					}
				}
				m.mu.Unlock()

				if evt.Event == "timeout" && !m.shuttingDown.Load() {
					go func(c *whatsmeow.Client) {
						time.Sleep(1500 * time.Millisecond)
						m.mu.RLock()
						stillActive := m.client == c && m.container != nil
						m.mu.RUnlock()
						if stillActive && !m.shuttingDown.Load() {
							_ = m.StartConnect()
						}
					}(boundCli)
				}
			}
		}(cli)
	}

	if err := cli.Connect(); err != nil {
		if errors.Is(err, whatsmeow.ErrAlreadyConnected) {
			return nil
		}
		m.mu.Lock()
		if m.client == cli {
			if m.state == "connecting" {
				m.state = "disconnected"
			}
			if (errors.Is(err, store.ErrDeviceDeleted) || (cli.Store != nil && cli.Store.Deleted)) && m.container != nil {
				_ = m.resetClientLocked()
			}
		}
		m.mu.Unlock()
		return err
	}

	return nil
}

func (m *Manager) setQR(qrRaw string) {
	m.mu.Lock()
	defer m.mu.Unlock()

	m.currentQrRaw = qrRaw
	m.state = "qr_ready"

	// Generate PNG image in base64 data url
	pngBytes, err := qrcode.Encode(qrRaw, qrcode.Medium, 256)
	if err == nil {
		m.currentQrImage = "data:image/png;base64," + base64.StdEncoding.EncodeToString(pngBytes)
	}
	fmt.Printf("[WHATSAPP] QR Code baru siap dipindai di Web Dashboard atau /qr\n")
}

// RequestPairingCode requests an 8-digit pairing code for linking without camera
func (m *Manager) RequestPairingCode(phone string) (string, error) {
	if config.Get().Snapshot().WhatsAppMode == "cloud" {
		return "", fmt.Errorf("pairing code tidak digunakan pada WhatsApp Business Cloud API")
	}
	m.mu.Lock()
	defer m.mu.Unlock()

	if m.client == nil {
		return "", fmt.Errorf("client WhatsApp belum diinisialisasi")
	}

	clean := otp.CleanPhoneNumber(phone)
	if clean == "" {
		return "", fmt.Errorf("nomor telepon tidak valid")
	}

	code, err := m.client.PairPhone(context.Background(), clean, true, whatsmeow.PairClientChrome, "Chrome (Linux)")
	if err != nil {
		return "", fmt.Errorf("gagal meminta kode pairing: %w", err)
	}

	m.currentPairCode = code
	fmt.Printf("[WHATSAPP] kode pairing dibuat untuk satu sesi dashboard\n")
	return code, nil
}

// IsOnWhatsApp verifies whether a phone number is registered on WhatsApp
func (m *Manager) IsOnWhatsApp(phone string) (bool, types.JID, error) {
	clean := otp.CleanPhoneNumber(phone)
	if clean == "" {
		return false, types.EmptyJID, fmt.Errorf("nomor tidak valid")
	}
	if config.Get().Snapshot().WhatsAppMode == "cloud" {
		// Cloud API performs recipient validation as part of the send request.
		return true, types.NewJID(clean, types.DefaultUserServer), nil
	}

	m.mu.RLock()
	cli := m.client
	m.mu.RUnlock()

	if cli == nil || !cli.IsConnected() {
		return false, types.EmptyJID, fmt.Errorf("WhatsApp belum terhubung")
	}

	resp, err := cli.IsOnWhatsApp(context.Background(), []string{clean})
	if err != nil {
		return false, types.EmptyJID, err
	}

	if len(resp) == 0 || !resp[0].IsIn {
		return false, types.EmptyJID, nil
	}

	return true, resp[0].JID, nil
}

// ResolveTargetJID parses and normalizes a phone number, WhatsApp LID, or full JID.
func ResolveTargetJID(target string) (string, types.JID, error) {
	t := strings.TrimSpace(target)
	if t == "" {
		return "", types.EmptyJID, fmt.Errorf("nomor tujuan kosong")
	}

	// 1. If target is already a formatted JID with server (e.g. 109899623206964@lid or 628...@s.whatsapp.net)
	if strings.Contains(t, "@") {
		parsed, err := types.ParseJID(t)
		if err == nil && parsed.User != "" {
			return parsed.User, parsed, nil
		}
	}

	// Extract digits
	var digits strings.Builder
	for _, r := range t {
		if r >= '0' && r <= '9' {
			digits.WriteRune(r)
		}
	}
	clean := digits.String()
	if clean == "" {
		return "", types.EmptyJID, fmt.Errorf("nomor tujuan tidak valid")
	}

	// 2. WhatsApp Linked ID (LID): typically 15-20 digits
	if len(clean) >= 15 {
		return clean, types.NewJID(clean, types.HiddenUserServer), nil
	}

	// 3. Phone number: normalize leading 0 to 62
	if strings.HasPrefix(clean, "0") {
		clean = "62" + clean[1:]
	} else if !strings.HasPrefix(clean, "62") && len(clean) >= 9 && len(clean) <= 10 {
		clean = "62" + clean
	}

	if len(clean) < 8 || len(clean) > 16 {
		return "", types.EmptyJID, fmt.Errorf("panjang nomor telepon tidak valid")
	}

	return clean, types.NewJID(clean, types.DefaultUserServer), nil
}

// SendMessage sends a text message to the specified phone number with anti-ban pacing
func (m *Manager) SendMessage(toPhone, message string) (string, error) {
	if strings.TrimSpace(message) == "" || utf8.RuneCountInString(message) > 4096 {
		return "", fmt.Errorf("isi pesan tidak valid")
	}

	clean, jid, err := ResolveTargetJID(toPhone)
	if err != nil {
		return "", err
	}
	if config.Get().Snapshot().WhatsAppMode == "cloud" {
		id, err := m.sendCloudText(clean, message)
		if err != nil {
			audit.Get().Record(clean, "manual_send", message, "failed", err.Error())
			return "", err
		}
		audit.Get().Record(clean, "manual_send", message, "success", "")
		return id, nil
	}
	if config.Get().Snapshot().AppEnv == "production" {
		return "", fmt.Errorf("pengiriman WhatsApp tidak tersedia: koneksi Cloud API belum aktif")
	}

	m.mu.RLock()
	cli := m.client
	connected := cli != nil && cli.IsConnected()
	m.mu.RUnlock()

	if !connected {
		return "", fmt.Errorf("WhatsApp belum terhubung; pesan tidak dikirim")
	}

	// Verify number is registered on WhatsApp if target is standard user phone (not LID)
	if jid.Server == types.DefaultUserServer {
		isOnWA, verifiedJid, err := m.IsOnWhatsApp(clean)
		if err != nil {
			return "", fmt.Errorf("gagal memvalidasi nomor di WhatsApp: %w", err)
		}
		if !isOnWA {
			return "", fmt.Errorf("nomor %s tidak terdaftar di WhatsApp", clean)
		}
		jid = verifiedJid
	}

	// Anti-ban human pacing: send presence composing
	_ = cli.SendChatPresence(context.Background(), jid, types.ChatPresenceComposing, types.ChatPresenceMediaText)
	time.Sleep(1 * time.Second)

	msg := &waE2E.Message{
		Conversation: proto.String(message),
	}

	resp, err := cli.SendMessage(context.Background(), jid, msg)
	if err != nil {
		audit.Get().Record(clean, "manual_send", message, "failed", err.Error())
		return "", fmt.Errorf("gagal mengirim pesan: %w", err)
	}

	audit.Get().Record(clean, "manual_send", message, "success", "")
	return resp.ID, nil
}

// SendOTPMessage uses an approved WhatsApp template when configured for the
// Business Cloud API. Development-only WhatsApp Web mode uses the local text
// template so it can never be mistaken for the production transport.
func (m *Manager) SendOTPMessage(toPhone, code, purpose, textMessage string, expiryMinutes int, appName string) (string, error) {
	clean := otp.CleanPhoneNumber(toPhone)
	if clean == "" {
		return "", fmt.Errorf("nomor tujuan tidak valid")
	}
	cfg := config.Get().Snapshot()
	if cfg.WhatsAppMode == "cloud" {
		template := cfg.WhatsAppOTPTemplateDefault
		if purpose == "reset" || purpose == "reset_password" {
			template = cfg.WhatsAppOTPTemplateReset
		}
		return m.sendCloudOTPTemplate(clean, template, cfg.WhatsAppOTPLanguage, code)
	}
	if cfg.AppEnv == "production" {
		return "", fmt.Errorf("OTP tidak dapat dikirim tanpa WhatsApp Business Cloud API")
	}
	return m.SendMessage(clean, textMessage)
}

// GetStatus returns the current status snapshot
func (m *Manager) GetStatus() GatewayStatus {
	m.mu.RLock()
	defer m.mu.RUnlock()

	uptime := int64(time.Since(m.startTime).Seconds())
	paired := m.state == "connected"

	return GatewayStatus{
		Service:       config.Get().Snapshot().AppName + " WhatsApp Gateway",
		Status:        m.state,
		Paired:        paired,
		QrImage:       m.currentQrImage,
		PairingCode:   m.currentPairCode,
		User:          m.connectedUser,
		UptimeSeconds: uptime,
		Sandbox:       !paired,
		Timestamp:     time.Now().Format(time.RFC3339),
	}
}

type InternalQR struct {
	Raw    string
	Image  string
	Status string
	Paired bool
}

// GetInternalQR returns the raw pairing token and rendered QR image for
// gateway-secret-authenticated integrations such as FastCargo Admin Portal.
func (m *Manager) GetInternalQR() InternalQR {
	m.mu.RLock()
	defer m.mu.RUnlock()

	return InternalQR{
		Raw:    m.currentQrRaw,
		Image:  m.currentQrImage,
		Status: m.state,
		Paired: m.state == "connected",
	}
}

// Logout clears credentials and resets connection
func (m *Manager) Logout() error {
	return m.resetSession(true)
}

func (m *Manager) resetSession(sendRemoteLogout bool) error {
	if config.Get().Snapshot().WhatsAppMode == "cloud" {
		return fmt.Errorf("sesi Cloud API dikelola pada Meta Business Manager")
	}

	m.mu.Lock()
	cli := m.client
	m.state = "disconnected"
	m.currentQrRaw = ""
	m.currentQrImage = ""
	m.currentPairCode = ""
	m.connectedUser = nil
	m.mu.Unlock()

	if cli != nil {
		if sendRemoteLogout && cli.Store != nil && !cli.Store.Deleted && cli.Store.ID != nil && cli.IsConnected() {
			ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
			_ = cli.Logout(ctx)
			cancel()
		}
		cli.Disconnect()
		if cli.Store != nil && !cli.Store.Deleted {
			_ = cli.Store.Delete(context.Background())
		}
	}

	m.mu.Lock()
	var resetErr error
	if m.client == cli && m.container != nil {
		resetErr = m.resetClientLocked()
	}
	m.mu.Unlock()

	if resetErr != nil {
		return resetErr
	}

	go func() {
		time.Sleep(1500 * time.Millisecond)
		if !m.shuttingDown.Load() {
			_ = m.StartConnect()
		}
	}()

	return nil
}

// Restart disconnects and reconnects the socket
func (m *Manager) Restart() error {
	if config.Get().Snapshot().WhatsAppMode == "cloud" {
		return m.StartConnect()
	}

	m.mu.Lock()
	cli := m.client
	m.state = "disconnected"
	m.currentQrRaw = ""
	m.currentQrImage = ""
	m.mu.Unlock()

	if cli != nil {
		cli.Disconnect()
	}

	go func() {
		time.Sleep(1 * time.Second)
		if !m.shuttingDown.Load() {
			_ = m.StartConnect()
		}
	}()

	return nil
}

// Shutdown releases the legacy socket and SQLite resources on process exit.
func (m *Manager) Shutdown() error {
	m.shuttingDown.Store(true)
	m.mu.Lock()
	inboxCancel, inboxDone := m.inboxCancel, m.inboxDone
	m.inboxCancel = nil
	m.mu.Unlock()
	if inboxCancel != nil {
		inboxCancel()
		select {
		case <-inboxDone:
		case <-time.After(3 * time.Second):
		}
	}
	m.mu.Lock()
	if m.client != nil {
		m.client.Disconnect()
	}
	m.mu.Unlock()
	m.debounceMu.Lock()
	for _, entry := range m.debounce {
		if entry.timer != nil && entry.timer.Stop() {
			m.dispatchWG.Done()
		}
	}
	m.debounce = make(map[string]*debounceEntry)
	m.debounceMu.Unlock()
	m.dispatchWG.Wait()
	m.mu.Lock()
	defer m.mu.Unlock()
	if m.db != nil {
		return m.db.Close()
	}
	return nil
}
