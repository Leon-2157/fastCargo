package whatsapp

import (
	"context"
	"database/sql"
	"path/filepath"
	"testing"
	"time"

	"go.mau.fi/whatsmeow/types"

	"wa-gateway/internal/config"
)

func TestEnsureInboxRetryColumnMigratesExistingDatabase(t *testing.T) {
	db, err := sql.Open("sqlite", filepath.Join(t.TempDir(), "old.db"))
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	if _, err := db.Exec(`CREATE TABLE cloud_inbox_messages (
		message_id TEXT PRIMARY KEY, phone TEXT NOT NULL, contact_name TEXT NOT NULL DEFAULT '',
		body TEXT NOT NULL, status TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL
	)`); err != nil {
		t.Fatal(err)
	}
	if _, err := db.Exec(`INSERT INTO cloud_inbox_messages(message_id, phone, body, status, created_at, updated_at) VALUES('old-id', '6281234567890', 'private', 'pending', 1, 1)`); err != nil {
		t.Fatal(err)
	}
	if err := ensureInboxRetryColumn(db); err != nil {
		t.Fatal(err)
	}
	if err := ensureInboxRetryColumn(db); err != nil {
		t.Fatalf("migration must be idempotent: %v", err)
	}
	var retryAt int64
	if err := db.QueryRow(`SELECT next_attempt_at FROM cloud_inbox_messages WHERE message_id='old-id'`).Scan(&retryAt); err != nil {
		t.Fatal(err)
	}
	if retryAt != 0 {
		t.Fatalf("existing inbox row next_attempt_at=%d, want default 0", retryAt)
	}
}

func TestOTPTemplateComponentsPutCodeInBodyAndButton(t *testing.T) {
	components, err := otpTemplateComponents("123456")
	if err != nil {
		t.Fatal(err)
	}
	if len(components) != 2 || components[0]["type"] != "body" || components[1]["type"] != "button" {
		t.Fatalf("unexpected OTP template component shape: %#v", components)
	}
	bodyParams, ok := components[0]["parameters"].([]map[string]string)
	if !ok || len(bodyParams) != 1 || bodyParams[0]["text"] != "123456" {
		t.Fatalf("OTP body parameter missing: %#v", components[0])
	}
	button := components[1]
	buttonParams, ok := button["parameters"].([]map[string]string)
	if button["sub_type"] != "url" || button["index"] != "0" || !ok || len(buttonParams) != 1 || buttonParams[0]["text"] != "123456" {
		t.Fatalf("OTP button parameter missing: %#v", button)
	}
	if _, err := otpTemplateComponents("12345x"); err == nil {
		t.Fatal("non-numeric OTP code accepted")
	}
}

func TestDeliveryOutcomeUnknownErrorIsClassified(t *testing.T) {
	if !DeliveryOutcomeUnknown(DeliveryOutcomeUnknownError{}) {
		t.Fatal("ambiguous send outcome was not classified")
	}
	if DeliveryOutcomeUnknown(context.Canceled) {
		t.Fatal("local error without an ambiguous send result was misclassified")
	}
}

func TestCloudInboxDeduplicatesAndErasesProcessedMessage(t *testing.T) {
	dbPath := filepath.Join(t.TempDir(), "gateway.db")
	t.Setenv("APP_ENV", "development")
	t.Setenv("CONFIG_FILE", "")
	t.Setenv("WHATSAPP_MODE", "cloud")
	t.Setenv("SESSION_DB_PATH", dbPath)
	t.Setenv("AI_ENABLED", "false")
	t.Setenv("AI_DEBOUNCE_SECONDS", "0.2")
	config.Get()

	manager := &Manager{}
	if err := manager.Init(); err != nil {
		t.Fatal(err)
	}
	defer manager.Shutdown()
	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()
	accepted, err := manager.QueueCloudIncoming(ctx, "wamid.test-1", "6281234567890", "Test Contact", "pesan rahasia")
	if err != nil || !accepted {
		t.Fatalf("first webhook event should be accepted: accepted=%t err=%v", accepted, err)
	}
	accepted, err = manager.QueueCloudIncoming(ctx, "wamid.test-1", "6281234567890", "Test Contact", "pesan rahasia")
	if err != nil || accepted {
		t.Fatalf("duplicate webhook event should be ignored: accepted=%t err=%v", accepted, err)
	}
	deadline := time.Now().Add(3 * time.Second)
	for time.Now().Before(deadline) {
		var status, body, name, phone string
		err := manager.db.QueryRow(`SELECT status, body, contact_name, phone FROM cloud_inbox_messages WHERE message_id='wamid.test-1'`).Scan(&status, &body, &name, &phone)
		if err != nil {
			t.Fatal(err)
		}
		if status == "processed" {
			if body != "" || name != "" || phone != "" {
				t.Fatal("processed inbox retained message content or contact details")
			}
			if _, err := manager.db.Exec(`UPDATE cloud_inbox_messages SET updated_at=1 WHERE message_id='wamid.test-1'`); err != nil {
				t.Fatal(err)
			}
			manager.cleanupCloudInbox()
			var count int
			if err := manager.db.QueryRow(`SELECT COUNT(*) FROM cloud_inbox_messages WHERE message_id='wamid.test-1'`).Scan(&count); err != nil {
				t.Fatal(err)
			}
			if count != 0 {
				t.Fatal("processed inbox record was not removed after retention")
			}
			return
		}
		time.Sleep(25 * time.Millisecond)
	}
	t.Fatal("inbound webhook event was not processed before deadline")
}

func TestResetSessionReplacesDeletedWhatsmeowDevice(t *testing.T) {
	dbPath := filepath.Join(t.TempDir(), "whatsmeow.db")
	t.Setenv("APP_ENV", "development")
	t.Setenv("CONFIG_FILE", "")
	t.Setenv("WHATSAPP_MODE", "whatsmeow")
	t.Setenv("SESSION_DB_PATH", dbPath)
	config.Reload()

	manager := &Manager{}
	if err := manager.Init(); err != nil {
		t.Fatal(err)
	}
	// Prevent the async reconnect goroutine from hitting the network during unit tests.
	defer manager.Shutdown()

	oldCli := manager.client
	if oldCli == nil || oldCli.Store == nil {
		t.Fatal("expected initialized whatsmeow client and store")
	}

	jid := types.NewJID("6281234567890", types.DefaultUserServer)
	oldCli.Store.ID = &jid
	if err := oldCli.Store.Delete(context.Background()); err != nil {
		t.Fatalf("failed to mark store deleted: %v", err)
	}
	if !oldCli.Store.Deleted {
		t.Fatal("expected old store to be marked Deleted")
	}

	manager.shuttingDown.Store(true)
	if err := manager.resetSession(false); err != nil {
		t.Fatalf("resetSession failed: %v", err)
	}

	if manager.client == nil || manager.client == oldCli {
		t.Fatal("expected resetSession to construct a fresh whatsmeow client")
	}
	if manager.client.Store.Deleted {
		t.Fatal("expected fresh client store not to be deleted")
	}
	if manager.client.Store.ID != nil {
		t.Fatalf("expected unpaired Store.ID after logout/unlink, got %v", manager.client.Store.ID)
	}
	if _, err := manager.client.GetQRChannel(context.Background()); err != nil {
		t.Fatalf("expected GetQRChannel on fresh client to succeed, got: %v", err)
	}
	status := manager.GetStatus()
	if status.Paired || status.Status != "disconnected" || status.User != nil {
		t.Fatalf("unexpected status after resetSession: %#v", status)
	}
}
