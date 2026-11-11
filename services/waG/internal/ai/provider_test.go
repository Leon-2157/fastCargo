package ai

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"sync/atomic"
	"testing"
	"time"
	"unicode/utf8"
)

func TestGenerateReplyBoundsInputAndSanitizesProviderFailures(t *testing.T) {
	var mode atomic.Int32
	var captured OpenAIChatRequest
	var captureMu sync.Mutex
	redirectTargetHit := atomic.Bool{}
	redirectTarget := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		redirectTargetHit.Store(true)
		w.WriteHeader(http.StatusOK)
	}))
	defer redirectTarget.Close()
	provider := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer test-provider-key" {
			t.Errorf("provider authorization header missing")
		}
		switch mode.Load() {
		case 0:
			captureMu.Lock()
			err := json.NewDecoder(r.Body).Decode(&captured)
			captureMu.Unlock()
			if err != nil {
				t.Errorf("decode provider request: %v", err)
			}
			_, _ = w.Write([]byte(`{"choices":[{"message":{"role":"assistant","content":"jawaban aman"}}]}`))
		case 1:
			w.WriteHeader(http.StatusInternalServerError)
			_, _ = w.Write([]byte("PROVIDER_RESPONSE_SECRET"))
		default:
			http.Redirect(w, r, redirectTarget.URL, http.StatusFound)
		}
	}))
	defer provider.Close()
	t.Setenv("APP_ENV", "development")
	t.Setenv("CONFIG_FILE", "")
	t.Setenv("AI_ENABLED", "true")
	t.Setenv("AI_BASE_URL", provider.URL)
	t.Setenv("AI_API_KEY", "test-provider-key")
	t.Setenv("AI_MODEL", "test-model")
	t.Setenv("AI_KNOWLEDGE_BASE_PATH", filepath.Join(t.TempDir(), "knowledge.md"))
	t.Setenv("AI_MAX_CONTEXT_TURNS", "8")
	t.Setenv("AI_CONTEXT_TTL_MINUTES", "30")
	t.Setenv("AI_DEBOUNCE_SECONDS", "1")
	t.Setenv("AI_FALLBACK_MESSAGE", "fallback")

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	input := strings.Repeat("🙂", 1201)
	reply, err := GetClient().GenerateReply(ctx, "6281234567890", input)
	if err != nil {
		t.Fatalf("successful provider response failed: %v", err)
	}
	if reply != "jawaban aman" {
		t.Fatalf("reply=%q", reply)
	}
	captureMu.Lock()
	lastUserMessage := captured.Messages[len(captured.Messages)-1].Content
	captureMu.Unlock()
	if !utf8.ValidString(lastUserMessage) || utf8.RuneCountInString(lastUserMessage) != 1200 {
		t.Fatal("user input was not safely truncated by Unicode characters")
	}

	mode.Store(1)
	_, err = GetClient().GenerateReply(ctx, "6281234567890", "trigger failure")
	if err == nil || strings.Contains(err.Error(), "PROVIDER_RESPONSE_SECRET") {
		t.Fatalf("provider error body leaked or error was missing: %v", err)
	}

	mode.Store(2)
	_, err = GetClient().GenerateReply(ctx, "6281234567890", "trigger redirect")
	if err == nil {
		t.Fatal("redirect response should fail")
	}
	if redirectTargetHit.Load() {
		t.Fatal("provider redirect was followed")
	}
}

func TestKnowledgeBaseRejectsOversizedUpdates(t *testing.T) {
	kb := &KnowledgeBase{filePath: filepath.Join(t.TempDir(), "knowledge.md")}
	if err := kb.UpdateContent(strings.Repeat("x", maxKnowledgeBytes+1)); err == nil {
		t.Fatal("oversized knowledge base was accepted")
	}
	if err := kb.UpdateContent("safe content"); err != nil {
		t.Fatal(err)
	}
	if got := kb.GetContent(); got != "safe content" {
		t.Fatalf("unexpected knowledge content %q", got)
	}
	info, err := os.Stat(kb.filePath)
	if err != nil {
		t.Fatal(err)
	}
	if info.Mode().Perm() != 0600 {
		t.Fatalf("knowledge permissions=%#o, want 0600", info.Mode().Perm())
	}
}
