package queue

import (
	"context"
	"crypto/rand"
	"database/sql"
	"encoding/hex"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"sync"
	"time"
	"unicode/utf8"

	_ "modernc.org/sqlite"

	"wa-gateway/internal/otp"
	"wa-gateway/internal/whatsapp"
)

const maxAttempts = 5
const maxPendingJobs = 10000

type JobStatus struct {
	ID        string    `json:"job_id"`
	Status    string    `json:"status"`
	Delivery  string    `json:"delivery_status,omitempty"`
	Attempts  int       `json:"attempts"`
	CreatedAt time.Time `json:"created_at"`
	UpdatedAt time.Time `json:"updated_at"`
	MessageID string    `json:"message_id,omitempty"`
	Error     string    `json:"error,omitempty"`
}

type WorkerQueue struct {
	db       *sql.DB
	ctx      context.Context
	cancel   context.CancelFunc
	done     chan struct{}
	interval time.Duration
}

var (
	queueMu      sync.RWMutex
	defaultQueue *WorkerQueue
)

// Init opens the persistent queue before the HTTP server starts. The queue
// shares the service SQLite file but uses its own bounded database handle.
func Init(path string) error {
	queueMu.Lock()
	defer queueMu.Unlock()
	if defaultQueue != nil {
		return nil
	}
	dir := filepath.Dir(path)
	if err := os.MkdirAll(dir, 0700); err != nil {
		return fmt.Errorf("gagal membuat direktori antrean")
	}
	_ = os.Chmod(dir, 0700)
	absPath, err := filepath.Abs(path)
	if err != nil {
		return fmt.Errorf("lokasi database antrean tidak valid")
	}
	dsn := "file:" + filepath.ToSlash(absPath) + "?_pragma=journal_mode(WAL)&_pragma=busy_timeout(5000)&_pragma=synchronous(FULL)"
	db, err := sql.Open("sqlite", dsn)
	if err != nil {
		return fmt.Errorf("gagal membuka database antrean")
	}
	db.SetMaxOpenConns(1)
	if err := db.Ping(); err != nil {
		_ = db.Close()
		return fmt.Errorf("gagal mengakses database antrean")
	}
	if err := os.Chmod(absPath, 0600); err != nil {
		_ = db.Close()
		return fmt.Errorf("izin database antrean tidak dapat diamankan")
	}
	_, err = db.Exec(`CREATE TABLE IF NOT EXISTS async_outbound_jobs (
		job_id TEXT PRIMARY KEY,
		phone TEXT NOT NULL,
		message TEXT NOT NULL,
		status TEXT NOT NULL,
		attempts INTEGER NOT NULL DEFAULT 0,
		created_at INTEGER NOT NULL,
		updated_at INTEGER NOT NULL,
		next_attempt_at INTEGER NOT NULL,
		message_id TEXT NOT NULL DEFAULT '',
		delivery_status TEXT NOT NULL DEFAULT 'queued',
		last_error TEXT NOT NULL DEFAULT ''
	)`)
	if err != nil {
		_ = db.Close()
		return fmt.Errorf("gagal menyiapkan skema antrean")
	}
	if _, err = db.Exec(`CREATE INDEX IF NOT EXISTS idx_async_outbound_due ON async_outbound_jobs(status, next_attempt_at, created_at)`); err != nil {
		_ = db.Close()
		return fmt.Errorf("gagal menyiapkan indeks antrean")
	}
	if _, err = db.Exec(`CREATE TABLE IF NOT EXISTS async_delivery_events (
		message_id TEXT PRIMARY KEY,
		delivery_status TEXT NOT NULL,
		updated_at INTEGER NOT NULL
	)`); err != nil {
		_ = db.Close()
		return fmt.Errorf("gagal menyiapkan status delivery")
	}
	// A process may stop while a job is in flight. Retry it after restart;
	// delivery is at-least-once because the provider does not expose a send key.
	_, err = db.Exec(`UPDATE async_outbound_jobs SET status='pending', updated_at=?, next_attempt_at=? WHERE status='processing'`, time.Now().Unix(), time.Now().Unix())
	if err != nil {
		_ = db.Close()
		return fmt.Errorf("gagal memulihkan pekerjaan antrean")
	}
	ctx, cancel := context.WithCancel(context.Background())
	q := &WorkerQueue{db: db, ctx: ctx, cancel: cancel, done: make(chan struct{}), interval: 1500 * time.Millisecond}
	defaultQueue = q
	go q.run()
	return nil
}

func Get() *WorkerQueue {
	queueMu.RLock()
	defer queueMu.RUnlock()
	return defaultQueue
}

func (q *WorkerQueue) EnqueueAsync(phone, message string) (string, error) {
	if q == nil || q.db == nil {
		return "", errors.New("antrean belum siap")
	}
	cleanPhone := otp.CleanPhoneNumber(phone)
	if cleanPhone == "" || message == "" || utf8.RuneCountInString(message) > 4096 {
		return "", errors.New("nomor atau isi pesan tidak valid")
	}
	jobID, err := newJobID()
	if err != nil {
		return "", errors.New("gagal membuat ID pekerjaan")
	}
	now := time.Now().Unix()
	tx, err := q.db.BeginTx(q.ctx, nil)
	if err != nil {
		return "", errors.New("antrean belum dapat menerima pesan")
	}
	defer tx.Rollback()
	var pending int
	if err := tx.QueryRowContext(q.ctx, `SELECT COUNT(*) FROM async_outbound_jobs WHERE status IN ('pending','processing')`).Scan(&pending); err != nil {
		return "", errors.New("antrean belum dapat menerima pesan")
	}
	if pending >= maxPendingJobs {
		return "", errors.New("antrean penuh; coba kembali nanti")
	}
	_, err = tx.ExecContext(q.ctx, `INSERT INTO async_outbound_jobs(job_id, phone, message, status, attempts, created_at, updated_at, next_attempt_at, delivery_status) VALUES(?, ?, ?, 'pending', 0, ?, ?, ?, 'queued')`, jobID, cleanPhone, message, now, now, now)
	if err != nil {
		return "", errors.New("gagal memasukkan pesan ke antrean")
	}
	if err := tx.Commit(); err != nil {
		return "", errors.New("gagal memasukkan pesan ke antrean")
	}
	return jobID, nil
}

func (q *WorkerQueue) GetStatus(jobID string) (JobStatus, error) {
	if q == nil || q.db == nil {
		return JobStatus{}, errors.New("antrean belum siap")
	}
	var status JobStatus
	var created, updated int64
	err := q.db.QueryRow(`SELECT job_id, status, attempts, created_at, updated_at, message_id, delivery_status, last_error FROM async_outbound_jobs WHERE job_id=?`, jobID).Scan(&status.ID, &status.Status, &status.Attempts, &created, &updated, &status.MessageID, &status.Delivery, &status.Error)
	if err != nil {
		return JobStatus{}, err
	}
	status.CreatedAt = time.Unix(created, 0).UTC()
	status.UpdatedAt = time.Unix(updated, 0).UTC()
	return status, nil
}

func (q *WorkerQueue) run() {
	defer close(q.done)
	ticker := time.NewTicker(500 * time.Millisecond)
	defer ticker.Stop()
	var lastSend time.Time
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
		if !lastSend.IsZero() && time.Since(lastSend) < q.interval {
			continue
		}
		if err := q.processOne(); err != nil {
			continue
		}
		lastSend = time.Now()
	}
}

func (q *WorkerQueue) processOne() error {
	tx, err := q.db.BeginTx(q.ctx, nil)
	if err != nil {
		return err
	}
	defer tx.Rollback()
	var id, phone, message string
	var attempts int
	err = tx.QueryRowContext(q.ctx, `SELECT job_id, phone, message, attempts FROM async_outbound_jobs WHERE status='pending' AND next_attempt_at<=? ORDER BY created_at, job_id LIMIT 1`, time.Now().Unix()).Scan(&id, &phone, &message, &attempts)
	if err != nil {
		return err
	}
	if _, err = tx.ExecContext(q.ctx, `UPDATE async_outbound_jobs SET status='processing', updated_at=? WHERE job_id=? AND status='pending'`, time.Now().Unix(), id); err != nil {
		return err
	}
	if err = tx.Commit(); err != nil {
		return err
	}
	messageID, sendErr := whatsapp.GetManager().SendMessage(phone, message)
	now := time.Now()
	if sendErr == nil {
		finishTx, txErr := q.db.BeginTx(q.ctx, nil)
		if txErr != nil {
			return txErr
		}
		defer finishTx.Rollback()
		_, txErr = finishTx.ExecContext(q.ctx, `UPDATE async_outbound_jobs SET status='accepted', delivery_status='queued', attempts=attempts+1, updated_at=?, message_id=?, last_error='', phone='', message='' WHERE job_id=? AND status='processing'`, now.Unix(), messageID, id)
		if txErr != nil {
			return txErr
		}
		var deliveryStatus string
		txErr = finishTx.QueryRowContext(q.ctx, `SELECT delivery_status FROM async_delivery_events WHERE message_id=?`, messageID).Scan(&deliveryStatus)
		if txErr == nil {
			_, txErr = finishTx.ExecContext(q.ctx, `UPDATE async_outbound_jobs SET delivery_status=?, status=CASE WHEN ?='failed' THEN 'delivery_failed' ELSE status END, last_error=CASE WHEN ?='failed' THEN 'WhatsApp melaporkan kegagalan delivery' ELSE last_error END WHERE job_id=?`, deliveryStatus, deliveryStatus, deliveryStatus, id)
			if txErr != nil {
				return txErr
			}
		} else if !errors.Is(txErr, sql.ErrNoRows) {
			return txErr
		}
		return finishTx.Commit()
	}
	attempts++
	if attempts >= maxAttempts {
		_, err = q.db.Exec(`UPDATE async_outbound_jobs SET status='failed', delivery_status='failed', attempts=?, updated_at=?, last_error='pengiriman gagal setelah batas retry', phone='', message='' WHERE job_id=? AND status='processing'`, attempts, now.Unix(), id)
		return err
	}
	delay := time.Duration(1<<min(attempts, 8)) * time.Second
	_, err = q.db.Exec(`UPDATE async_outbound_jobs SET status='pending', delivery_status='queued', attempts=?, updated_at=?, next_attempt_at=?, last_error='pengiriman gagal; akan dicoba kembali' WHERE job_id=? AND status='processing'`, attempts, now.Unix(), now.Add(delay).Unix(), id)
	return err
}

func (q *WorkerQueue) cleanup() {
	cutoff := time.Now().Add(-7 * 24 * time.Hour).Unix()
	_, _ = q.db.ExecContext(q.ctx, `DELETE FROM async_outbound_jobs WHERE status IN ('accepted','sent','failed','delivery_failed') AND updated_at<?`, cutoff)
	_, _ = q.db.ExecContext(q.ctx, `DELETE FROM async_delivery_events WHERE updated_at<?`, cutoff)
}

func UpdateDeliveryStatus(ctx context.Context, messageID, deliveryStatus string) error {
	if messageID == "" {
		return errors.New("message ID tidak valid")
	}
	switch deliveryStatus {
	case "sent", "delivered", "read", "failed":
	default:
		return errors.New("delivery status tidak valid")
	}
	q := Get()
	if q == nil || q.db == nil {
		return errors.New("antrean belum siap")
	}
	tx, err := q.db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	defer tx.Rollback()
	var previous string
	previousErr := tx.QueryRowContext(ctx, `SELECT delivery_status FROM async_delivery_events WHERE message_id=?`, messageID).Scan(&previous)
	if previousErr != nil && !errors.Is(previousErr, sql.ErrNoRows) {
		return previousErr
	}
	if previous == "" || deliveryRank(deliveryStatus) >= deliveryRank(previous) {
		now := time.Now().Unix()
		if _, err := tx.ExecContext(ctx, `INSERT INTO async_delivery_events(message_id, delivery_status, updated_at) VALUES(?, ?, ?) ON CONFLICT(message_id) DO UPDATE SET delivery_status=excluded.delivery_status, updated_at=excluded.updated_at`, messageID, deliveryStatus, now); err != nil {
			return err
		}
		lastError := ""
		state := "accepted"
		if deliveryStatus == "failed" {
			state = "delivery_failed"
			lastError = "WhatsApp melaporkan kegagalan delivery"
		}
		if _, err := tx.ExecContext(ctx, `UPDATE async_outbound_jobs SET delivery_status=?, status=CASE WHEN ?='delivery_failed' THEN ? ELSE status END, last_error=CASE WHEN ?='delivery_failed' THEN ? ELSE last_error END, updated_at=? WHERE message_id=?`, deliveryStatus, state, state, state, lastError, now, messageID); err != nil {
			return err
		}
	}
	return tx.Commit()
}

func deliveryRank(status string) int {
	switch status {
	case "sent":
		return 0
	case "delivered":
		return 1
	case "read":
		return 2
	case "failed":
		return 3
	default:
		return -1
	}
}

func (q *WorkerQueue) Shutdown(ctx context.Context) error {
	if q == nil {
		return nil
	}
	q.cancel()
	select {
	case <-q.done:
	case <-ctx.Done():
		return ctx.Err()
	}
	return q.db.Close()
}

func newJobID() (string, error) {
	var b [16]byte
	if _, err := rand.Read(b[:]); err != nil {
		return "", err
	}
	return hex.EncodeToString(b[:]), nil
}
