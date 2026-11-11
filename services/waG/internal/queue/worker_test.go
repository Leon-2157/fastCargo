package queue

import (
	"context"
	"database/sql"
	"path/filepath"
	"testing"

	_ "modernc.org/sqlite"
)

func TestAsyncJobEnqueueIsPersistentAndReturnsStatus(t *testing.T) {
	dbPath := filepath.Join(t.TempDir(), "queue.db")
	db, err := sql.Open("sqlite", "file:"+dbPath)
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	_, err = db.Exec(`CREATE TABLE async_outbound_jobs (
		job_id TEXT PRIMARY KEY, phone TEXT NOT NULL, message TEXT NOT NULL, status TEXT NOT NULL,
		attempts INTEGER NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL,
		next_attempt_at INTEGER NOT NULL, message_id TEXT NOT NULL DEFAULT '', delivery_status TEXT NOT NULL DEFAULT 'queued', last_error TEXT NOT NULL DEFAULT ''
	)`)
	if err != nil {
		t.Fatal(err)
	}
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	q := &WorkerQueue{db: db, ctx: ctx}
	jobID, err := q.EnqueueAsync("+62 812 3456 7890", "test message")
	if err != nil {
		t.Fatal(err)
	}
	if len(jobID) != 32 {
		t.Fatalf("unexpected job ID length: %d", len(jobID))
	}
	status, err := q.GetStatus(jobID)
	if err != nil {
		t.Fatal(err)
	}
	if status.Status != "pending" || status.Attempts != 0 {
		t.Fatalf("unexpected initial status: %#v", status)
	}
	var phone, message string
	if err := db.QueryRow(`SELECT phone, message FROM async_outbound_jobs WHERE job_id=?`, jobID).Scan(&phone, &message); err != nil {
		t.Fatal(err)
	}
	if phone != "6281234567890" || message != "test message" {
		t.Fatalf("job was not persisted correctly")
	}
}

func TestAsyncJobRejectsInvalidInput(t *testing.T) {
	db, err := sql.Open("sqlite", "file:"+filepath.Join(t.TempDir(), "queue.db"))
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	_, err = db.Exec(`CREATE TABLE async_outbound_jobs (
		job_id TEXT PRIMARY KEY, phone TEXT NOT NULL, message TEXT NOT NULL, status TEXT NOT NULL,
		attempts INTEGER NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL,
		next_attempt_at INTEGER NOT NULL, message_id TEXT NOT NULL DEFAULT '', delivery_status TEXT NOT NULL DEFAULT 'queued', last_error TEXT NOT NULL DEFAULT ''
	)`)
	if err != nil {
		t.Fatal(err)
	}
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	q := &WorkerQueue{db: db, ctx: ctx}
	if _, err := q.EnqueueAsync("0215550000", "text"); err == nil {
		t.Fatal("invalid target was accepted")
	}
	if _, err := q.EnqueueAsync("6281234567890", ""); err == nil {
		t.Fatal("empty message was accepted")
	}
}

func TestDeliveryStatusesArePersistedMonotonicallyBeforeAndAfterJobAcceptance(t *testing.T) {
	db, err := sql.Open("sqlite", "file:"+filepath.Join(t.TempDir(), "delivery.db"))
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	if _, err := db.Exec(`CREATE TABLE async_outbound_jobs (
		job_id TEXT PRIMARY KEY, status TEXT NOT NULL, message_id TEXT NOT NULL DEFAULT '',
		delivery_status TEXT NOT NULL DEFAULT 'queued', last_error TEXT NOT NULL DEFAULT '', updated_at INTEGER NOT NULL
	)`); err != nil {
		t.Fatal(err)
	}
	if _, err := db.Exec(`CREATE TABLE async_delivery_events (
		message_id TEXT PRIMARY KEY, delivery_status TEXT NOT NULL, updated_at INTEGER NOT NULL
	)`); err != nil {
		t.Fatal(err)
	}
	queueMu.Lock()
	previousQueue := defaultQueue
	defaultQueue = &WorkerQueue{db: db}
	queueMu.Unlock()
	defer func() {
		queueMu.Lock()
		defaultQueue = previousQueue
		queueMu.Unlock()
	}()

	if err := UpdateDeliveryStatus(context.Background(), "wamid.before-accept", "delivered"); err != nil {
		t.Fatal(err)
	}
	if _, err := db.Exec(`INSERT INTO async_outbound_jobs(job_id, status, message_id, delivery_status, updated_at) VALUES('job-1', 'accepted', 'wamid.before-accept', 'queued', 1)`); err != nil {
		t.Fatal(err)
	}
	if err := UpdateDeliveryStatus(context.Background(), "wamid.before-accept", "read"); err != nil {
		t.Fatal(err)
	}
	if err := UpdateDeliveryStatus(context.Background(), "wamid.before-accept", "sent"); err != nil {
		t.Fatal(err)
	}
	var status, delivery string
	if err := db.QueryRow(`SELECT status, delivery_status FROM async_outbound_jobs WHERE job_id='job-1'`).Scan(&status, &delivery); err != nil {
		t.Fatal(err)
	}
	if status != "accepted" || delivery != "read" {
		t.Fatalf("delivery state regressed: status=%q delivery=%q", status, delivery)
	}
	if err := UpdateDeliveryStatus(context.Background(), "wamid.before-accept", "failed"); err != nil {
		t.Fatal(err)
	}
	if err := db.QueryRow(`SELECT status, delivery_status FROM async_outbound_jobs WHERE job_id='job-1'`).Scan(&status, &delivery); err != nil {
		t.Fatal(err)
	}
	if status != "delivery_failed" || delivery != "failed" {
		t.Fatalf("failure status not applied: status=%q delivery=%q", status, delivery)
	}
}
