package forwardqueue

import (
	"bytes"
	"context"
	"crypto/rand"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"
	"unicode/utf8"

	_ "modernc.org/sqlite"

	"wa-gateway/internal/config"
)

const (
	maxAttempts = 5
	maxPending  = 10000
)

type Queue struct {
	db     *sql.DB
	ctx    context.Context
	cancel context.CancelFunc
	done   chan struct{}
}

type eventPayload struct {
	EventID   string `json:"event_id"`
	Sender    string `json:"sender"`
	Name      string `json:"name"`
	Message   string `json:"message"`
	Timestamp int64  `json:"timestamp"`
}

var (
	queueMu sync.RWMutex
	current *Queue
)

func Init(path string) error {
	queueMu.Lock()
	defer queueMu.Unlock()
	if current != nil {
		return nil
	}
	dir := filepath.Dir(path)
	if err := os.MkdirAll(dir, 0700); err != nil {
		return fmt.Errorf("gagal membuat direktori antrean webhook")
	}
	_ = os.Chmod(dir, 0700)
	absPath, err := filepath.Abs(path)
	if err != nil {
		return fmt.Errorf("lokasi database antrean webhook tidak valid")
	}
	dsn := "file:" + filepath.ToSlash(absPath) + "?_pragma=journal_mode(WAL)&_pragma=busy_timeout(5000)&_pragma=synchronous(FULL)"
	db, err := sql.Open("sqlite", dsn)
	if err != nil {
		return fmt.Errorf("gagal membuka database antrean webhook")
	}
	db.SetMaxOpenConns(1)
	if err := db.Ping(); err != nil {
		_ = db.Close()
		return fmt.Errorf("gagal mengakses database antrean webhook")
	}
	if err := os.Chmod(absPath, 0600); err != nil {
		_ = db.Close()
		return fmt.Errorf("izin database antrean webhook tidak dapat diamankan")
	}
	_, err = db.Exec(`CREATE TABLE IF NOT EXISTS outbound_webhook_jobs (
		job_id TEXT PRIMARY KEY,
		endpoint TEXT NOT NULL,
		sender TEXT NOT NULL,
		name TEXT NOT NULL,
		message TEXT NOT NULL,
		status TEXT NOT NULL,
		attempts INTEGER NOT NULL DEFAULT 0,
		created_at INTEGER NOT NULL,
		updated_at INTEGER NOT NULL,
		next_attempt_at INTEGER NOT NULL,
		last_error TEXT NOT NULL DEFAULT ''
	)`)
	if err != nil {
		_ = db.Close()
		return fmt.Errorf("gagal menyiapkan skema antrean webhook")
	}
	if _, err = db.Exec(`CREATE INDEX IF NOT EXISTS idx_outbound_webhook_due ON outbound_webhook_jobs(status, next_attempt_at, created_at)`); err != nil {
		_ = db.Close()
		return fmt.Errorf("gagal menyiapkan indeks antrean webhook")
	}
	now := time.Now().Unix()
	if _, err = db.Exec(`UPDATE outbound_webhook_jobs SET status='pending', updated_at=?, next_attempt_at=? WHERE status='processing'`, now, now); err != nil {
		_ = db.Close()
		return fmt.Errorf("gagal memulihkan antrean webhook")
	}
	ctx, cancel := context.WithCancel(context.Background())
	q := &Queue{db: db, ctx: ctx, cancel: cancel, done: make(chan struct{})}
	current = q
	go q.run()
	return nil
}

func Get() *Queue {
	queueMu.RLock()
	defer queueMu.RUnlock()
	return current
}

// Enqueue durably stores a webhook event before the caller marks its inbound
// message as processed. Delivery is at-least-once; event_id is stable so the
// receiver can deduplicate retries.
func (q *Queue) Enqueue(endpoint, sender, name, message string) (string, error) {
	if q == nil || q.db == nil {
		return "", errors.New("antrean webhook belum siap")
	}
	endpoint = strings.TrimSpace(endpoint)
	u, err := url.Parse(endpoint)
	cfg := config.Get().Snapshot()
	if err != nil || u.Host == "" || u.User != nil || u.Fragment != "" || u.RawQuery != "" || (cfg.AppEnv == "production" && u.Scheme != "https") || (u.Scheme != "http" && u.Scheme != "https") {
		return "", errors.New("URL webhook tidak valid")
	}
	if len(endpoint) > 2048 || utf8.RuneCountInString(sender) > 40 || utf8.RuneCountInString(name) > 120 || utf8.RuneCountInString(message) < 1 || utf8.RuneCountInString(message) > 4096 {
		return "", errors.New("data webhook di luar batas ukuran")
	}
	id, err := newID()
	if err != nil {
		return "", errors.New("gagal membuat ID webhook")
	}
	now := time.Now().Unix()
	tx, err := q.db.BeginTx(q.ctx, nil)
	if err != nil {
		return "", errors.New("antrean webhook belum dapat menerima pesan")
	}
	defer tx.Rollback()
	var pending int
	if err := tx.QueryRowContext(q.ctx, `SELECT COUNT(*) FROM outbound_webhook_jobs WHERE status IN ('pending','processing')`).Scan(&pending); err != nil || pending >= maxPending {
		return "", errors.New("antrean webhook penuh atau belum tersedia")
	}
	if _, err := tx.ExecContext(q.ctx, `INSERT INTO outbound_webhook_jobs(job_id, endpoint, sender, name, message, status, created_at, updated_at, next_attempt_at) VALUES(?, ?, ?, ?, ?, 'pending', ?, ?, ?)`, id, endpoint, sender, name, message, now, now, now); err != nil {
		return "", errors.New("gagal memasukkan event ke antrean webhook")
	}
	if err := tx.Commit(); err != nil {
		return "", errors.New("gagal memasukkan event ke antrean webhook")
	}
	return id, nil
}

func (q *Queue) run() {
	defer close(q.done)
	ticker := time.NewTicker(500 * time.Millisecond)
	defer ticker.Stop()
	var lastCleanup time.Time
	for {
		select {
		case <-q.ctx.Done():
			return
		case <-ticker.C:
		}
		if time.Since(lastCleanup) >= time.Hour {
			q.cleanup()
			lastCleanup = time.Now()
		}
		_ = q.processOne()
	}
}

func (q *Queue) processOne() error {
	tx, err := q.db.BeginTx(q.ctx, nil)
	if err != nil {
		return err
	}
	defer tx.Rollback()
	var id, endpoint, sender, name, message string
	var attempts int
	err = tx.QueryRowContext(q.ctx, `SELECT job_id, endpoint, sender, name, message, attempts FROM outbound_webhook_jobs WHERE status='pending' AND next_attempt_at<=? ORDER BY created_at, job_id LIMIT 1`, time.Now().Unix()).Scan(&id, &endpoint, &sender, &name, &message, &attempts)
	if err != nil {
		return err
	}
	if _, err = tx.ExecContext(q.ctx, `UPDATE outbound_webhook_jobs SET status='processing', updated_at=? WHERE job_id=? AND status='pending'`, time.Now().Unix(), id); err != nil {
		return err
	}
	if err = tx.Commit(); err != nil {
		return err
	}
	if err := q.send(id, endpoint, sender, name, message); err == nil {
		now := time.Now().Unix()
		_, err = q.db.ExecContext(q.ctx, `UPDATE outbound_webhook_jobs SET status='accepted', sender='', name='', message='', updated_at=?, last_error='' WHERE job_id=? AND status='processing'`, now, id)
		return err
	}
	attempts++
	now := time.Now()
	if attempts >= maxAttempts {
		_, err = q.db.ExecContext(q.ctx, `UPDATE outbound_webhook_jobs SET status='failed', sender='', name='', message='', attempts=?, updated_at=?, last_error='webhook gagal setelah batas retry' WHERE job_id=? AND status='processing'`, attempts, now.Unix(), id)
		return err
	}
	delay := time.Duration(1<<attempts) * time.Second
	_, err = q.db.ExecContext(q.ctx, `UPDATE outbound_webhook_jobs SET status='pending', attempts=?, updated_at=?, next_attempt_at=?, last_error='webhook gagal; akan dicoba kembali' WHERE job_id=? AND status='processing'`, attempts, now.Unix(), now.Add(delay).Unix(), id)
	return err
}

func (q *Queue) send(id, endpoint, sender, name, message string) error {
	payload, err := json.Marshal(eventPayload{EventID: id, Sender: sender, Name: name, Message: message, Timestamp: time.Now().Unix()})
	if err != nil {
		return err
	}
	ctx, cancel := context.WithTimeout(q.ctx, 5*time.Second)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, endpoint, bytes.NewReader(payload))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Idempotency-Key", id)
	if secret := config.Get().Snapshot().GatewaySecret; secret != "" {
		req.Header.Set("X-Gateway-Secret", secret)
	}
	client := &http.Client{Timeout: 5 * time.Second, CheckRedirect: func(_ *http.Request, _ []*http.Request) error { return http.ErrUseLastResponse }}
	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	_, _ = io.Copy(io.Discard, io.LimitReader(resp.Body, 64<<10))
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("endpoint returned HTTP %d", resp.StatusCode)
	}
	return nil
}

func (q *Queue) cleanup() {
	cutoff := time.Now().Add(-7 * 24 * time.Hour).Unix()
	_, _ = q.db.ExecContext(q.ctx, `DELETE FROM outbound_webhook_jobs WHERE status IN ('accepted','failed') AND updated_at<?`, cutoff)
}

func (q *Queue) Shutdown(ctx context.Context) error {
	if q == nil {
		return nil
	}
	q.cancel()
	select {
	case <-q.done:
	case <-ctx.Done():
		return ctx.Err()
	}
	if err := q.db.Close(); err != nil {
		return err
	}
	queueMu.Lock()
	if current == q {
		current = nil
	}
	queueMu.Unlock()
	return nil
}

func newID() (string, error) {
	var id [16]byte
	if _, err := rand.Read(id[:]); err != nil {
		return "", err
	}
	return hex.EncodeToString(id[:]), nil
}
