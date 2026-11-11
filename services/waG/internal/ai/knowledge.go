package ai

import (
	"fmt"
	"os"
	"path/filepath"
	"sync"
	"unicode/utf8"
)

const maxKnowledgeBytes = 32 << 10

type KnowledgeBase struct {
	mu       sync.RWMutex
	filePath string
	content  string
}

var (
	defaultKB *KnowledgeBase
	kbOnce    sync.Once
)

func GetKnowledgeBase(path string) *KnowledgeBase {
	kbOnce.Do(func() {
		defaultKB = &KnowledgeBase{
			filePath: path,
		}
		defaultKB.load()
	})
	return defaultKB
}

func (kb *KnowledgeBase) load() {
	kb.mu.Lock()
	defer kb.mu.Unlock()

	data, err := os.ReadFile(kb.filePath)
	if err == nil {
		if len(data) > maxKnowledgeBytes || !utf8.Valid(data) {
			kb.content = ""
			return
		}
		kb.content = string(data)
		_ = os.Chmod(kb.filePath, 0600)
		return
	}
	if !os.IsNotExist(err) {
		kb.content = ""
		return
	}
	// Create a default file only when the configured file is absent.
	defaultTemplate := `# Basis Pengetahuan & Panduan Asisten AI

Selamat datang di asisten resmi WhatsApp!
Gunakan file ini untuk mendefinisikan informasi bisnis Anda agar AI dapat menjawab pelanggan dengan akurat.

## Informasi Umum
- Nama Layanan: Layanan Resmi WhatsApp
- Jam Operasional: Setiap hari, 08.00 - 21.00 WIB
- Respon Cepat: Layanan customer service otomatis aktif 24 jam.

## Layanan Utama
1. Verifikasi akun & pengiriman kode OTP otomatis.
2. Informasi produk, pemesanan, dan pertanyaan umum.
3. Bantuan langsung dengan Admin CS manusia jika diperlukan.

## Kebijakan & FAQ
- Q: Berapa lama kode OTP berlaku?
  A: Kode OTP berlaku selama 5 menit sejak dikirim.
- Q: Apakah saya bisa berbicara langsung dengan staf manusia?
  A: Ya, ketik "hubungi admin" atau tunggu sebentar, admin kami akan segera merespons chat Anda.
`
	if seedPath := os.Getenv("AI_KNOWLEDGE_BASE_SEED"); seedPath != "" {
		if seed, seedErr := os.ReadFile(seedPath); seedErr == nil && len(seed) <= maxKnowledgeBytes && utf8.Valid(seed) {
			defaultTemplate = string(seed)
		}
	}
	_ = os.MkdirAll(filepath.Dir(kb.filePath), 0700)
	_ = os.WriteFile(kb.filePath, []byte(defaultTemplate), 0600)
	_ = os.Chmod(kb.filePath, 0600)
	kb.content = defaultTemplate
}

func (kb *KnowledgeBase) GetContent() string {
	kb.mu.RLock()
	defer kb.mu.RUnlock()
	return kb.content
}

func (kb *KnowledgeBase) UpdateContent(newContent string) error {
	if !utf8.ValidString(newContent) || len(newContent) > maxKnowledgeBytes {
		return fmt.Errorf("basis pengetahuan maksimal 32 KiB dan harus berupa UTF-8 valid")
	}
	kb.mu.Lock()
	defer kb.mu.Unlock()
	dir := filepath.Dir(kb.filePath)
	if err := os.MkdirAll(dir, 0700); err != nil {
		return err
	}
	tmp, err := os.CreateTemp(dir, ".knowledge-*.tmp")
	if err != nil {
		return err
	}
	tmpName := tmp.Name()
	defer os.Remove(tmpName)
	if err := tmp.Chmod(0600); err != nil {
		_ = tmp.Close()
		return err
	}
	if _, err := tmp.WriteString(newContent); err != nil {
		_ = tmp.Close()
		return err
	}
	if err := tmp.Sync(); err != nil {
		_ = tmp.Close()
		return err
	}
	if err := tmp.Close(); err != nil {
		return err
	}
	if err := os.Rename(tmpName, kb.filePath); err != nil {
		return err
	}
	kb.content = newContent
	return nil
}
