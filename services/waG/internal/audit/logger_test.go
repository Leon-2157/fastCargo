package audit

import (
	"strings"
	"testing"
)

func TestRecordRedactsPhoneAndMessageContent(t *testing.T) {
	logger := &Logger{entries: make([]LogEntry, 0), limit: 10, nextID: 1}
	logger.Record("6281234567890", "otp_send", "OTP: 123456", "success", "")
	entry := logger.GetRecent(1)[0]
	if entry.Phone == "6281234567890" {
		t.Fatal("full phone number was retained")
	}
	if strings.Contains(entry.Snippet, "123456") {
		t.Fatal("OTP code was retained in activity log")
	}
	if entry.Snippet != "Permintaan OTP" {
		t.Fatalf("unexpected activity snippet: %q", entry.Snippet)
	}
}
