package forwardqueue

import (
	"context"
	"database/sql"
	"net/http"
	"net/http/httptest"
	"os"
	"sync/atomic"
	"testing"
	"time"

	"wa-gateway/internal/config"
)

func TestWebhookQueueRetriesDurablyAndClearsPayload(t *testing.T) {
	t.Setenv("APP_ENV", "development")
	t.Setenv("CONFIG_FILE", "")
	config.Get()

	var attempts atomic.Int32
	var firstKey atomic.Value
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost || r.Header.Get("Content-Type") != "application/json" {
			t.Errorf("unexpected webhook request: %s %s", r.Method, r.URL.Path)
		}
		key := r.Header.Get("Idempotency-Key")
		if key == "" {
			t.Error("Idempotency-Key missing")
		}
		if n := attempts.Add(1); n == 1 {
			firstKey.Store(key)
			http.Error(w, "temporary failure", http.StatusServiceUnavailable)
			return
		}
		if key != firstKey.Load() {
			t.Error("retry did not preserve the event id")
		}
		w.WriteHeader(http.StatusAccepted)
	}))
	defer server.Close()

	path := t.TempDir() + "/queue.db"
	if err := Init(path); err != nil {
		t.Fatal(err)
	}
	q := Get()
	defer func() {
		ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer cancel()
		_ = q.Shutdown(ctx)
	}()
	id, err := q.Enqueue(server.URL, "6281234567890", "Test User", "message body")
	if err != nil {
		t.Fatal(err)
	}
	deadline := time.Now().Add(8 * time.Second)
	for time.Now().Before(deadline) {
		var status, sender, name, message string
		err := q.db.QueryRow(`SELECT status, sender, name, message FROM outbound_webhook_jobs WHERE job_id=?`, id).Scan(&status, &sender, &name, &message)
		if err != nil {
			t.Fatal(err)
		}
		if status == "accepted" {
			if sender != "" || name != "" || message != "" {
				t.Fatal("accepted job retained personal payload data")
			}
			if attempts.Load() < 2 {
				t.Fatal("failed delivery was not retried")
			}
			info, err := os.Stat(path)
			if err != nil {
				t.Fatal(err)
			}
			if info.Mode().Perm() != 0600 {
				t.Fatalf("database permissions=%#o, want 0600", info.Mode().Perm())
			}
			return
		}
		time.Sleep(100 * time.Millisecond)
	}
	t.Fatal("webhook job did not reach accepted status before the deadline")
}

func TestQueueRejectsInvalidEndpointAndOversizedMessage(t *testing.T) {
	db, err := sql.Open("sqlite", ":memory:")
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	q := &Queue{db: db}
	if _, err := q.Enqueue("file:///tmp/nope", "6281234567890", "", "message"); err == nil {
		t.Fatal("non-http URL accepted")
	}
	if _, err := q.Enqueue("http://localhost/hook", "6281234567890", "", string(make([]byte, 20<<10))); err == nil {
		t.Fatal("oversized webhook message accepted")
	}
}
