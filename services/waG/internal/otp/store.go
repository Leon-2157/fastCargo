package otp

import (
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"fmt"
	"math/big"
	"strings"
	"sync"
	"time"
)

type OtpRecord struct {
	CodeHash   [32]byte
	Purpose    string
	ExpiresAt  time.Time
	Attempts   int
	LastSentAt time.Time
}

type Store struct {
	mu      sync.RWMutex
	records map[string]*OtpRecord
	hashKey [32]byte
}

const maxActiveOTPs = 100000

var (
	defaultStore *Store
	once         sync.Once
)

func Get() *Store {
	once.Do(func() {
		defaultStore = &Store{
			records: make(map[string]*OtpRecord),
		}
		if _, err := rand.Read(defaultStore.hashKey[:]); err != nil {
			panic("secure random source unavailable")
		}
		go defaultStore.startCleaner()
	})
	return defaultStore
}

// CleanPhoneNumber normalizes Indonesian phone numbers into standard 62xxx
func CleanPhoneNumber(phone string) string {
	var digits strings.Builder
	digits.Grow(len(phone))
	for _, r := range phone {
		if r >= '0' && r <= '9' {
			digits.WriteRune(r)
		}
	}
	p := digits.String()
	if strings.HasPrefix(p, "0") {
		p = "62" + p[1:]
	} else if !strings.HasPrefix(p, "62") && len(p) >= 9 {
		p = "62" + p
	}
	if len(p) < 11 || len(p) > 14 || !strings.HasPrefix(p, "628") {
		return ""
	}
	return p
}

// Generate creates a 6-digit random OTP for the clean phone
func (s *Store) Generate(phone string, expiryMin, cooldownSec int) (string, error) {
	return s.GenerateForPurpose(phone, "default", expiryMin, cooldownSec)
}

func (s *Store) GenerateForPurpose(phone, purpose string, expiryMin, cooldownSec int) (string, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	clean := CleanPhoneNumber(phone)
	if clean == "" {
		return "", fmt.Errorf("nomor telepon tidak valid")
	}
	if expiryMin < 1 || expiryMin > 60 || cooldownSec < 1 || cooldownSec > 3600 {
		return "", fmt.Errorf("konfigurasi OTP tidak valid")
	}
	purpose = normalizePurpose(purpose)
	if purpose == "invalid" {
		return "", fmt.Errorf("tujuan OTP tidak valid")
	}

	now := time.Now()
	for existingPhone, record := range s.records {
		if !record.ExpiresAt.After(now) {
			delete(s.records, existingPhone)
		}
	}
	if existing, found := s.records[clean]; found {
		if now.Sub(existing.LastSentAt) < time.Duration(cooldownSec)*time.Second {
			remaining := cooldownSec - int(now.Sub(existing.LastSentAt).Seconds())
			return "", fmt.Errorf("mohon tunggu %d detik sebelum meminta OTP baru", remaining)
		}
	}
	if len(s.records) >= maxActiveOTPs {
		return "", fmt.Errorf("layanan OTP sedang sibuk; coba kembali nanti")
	}

	// Generate 6-digit code cryptographically
	n, err := rand.Int(rand.Reader, big.NewInt(900000))
	if err != nil {
		return "", fmt.Errorf("gagal menghasilkan kode acak: %w", err)
	}
	code := fmt.Sprintf("%06d", n.Int64()+100000)

	s.records[clean] = &OtpRecord{
		CodeHash:   s.hashCode(clean, code),
		Purpose:    purpose,
		ExpiresAt:  now.Add(time.Duration(expiryMin) * time.Minute),
		Attempts:   0,
		LastSentAt: now,
	}

	return code, nil
}

// Verify checks the provided OTP against stored records
func (s *Store) Verify(phone, inputCode string, maxAttempts int) (bool, string) {
	return s.VerifyForPurpose(phone, "default", inputCode, maxAttempts)
}

func (s *Store) VerifyForPurpose(phone, purpose, inputCode string, maxAttempts int) (bool, string) {
	s.mu.Lock()
	defer s.mu.Unlock()

	clean := CleanPhoneNumber(phone)
	if maxAttempts < 1 {
		maxAttempts = 5
	}
	record, found := s.records[clean]
	if !found {
		return false, "Kode OTP tidak ditemukan atau belum pernah diminta."
	}
	if record.Purpose != normalizePurpose(purpose) {
		return false, "Kode OTP tidak berlaku untuk tujuan ini."
	}

	if time.Now().After(record.ExpiresAt) {
		delete(s.records, clean)
		return false, "Kode OTP telah kedaluwarsa. Silakan minta kode baru."
	}

	if record.Attempts >= maxAttempts {
		delete(s.records, clean)
		return false, "Terlalu banyak percobaan salah. Silakan minta kode baru."
	}

	inputHash := s.hashCode(clean, strings.TrimSpace(inputCode))
	if len(inputCode) != 6 || !isDigits(inputCode) || !hmac.Equal(record.CodeHash[:], inputHash[:]) {
		record.Attempts++
		if record.Attempts >= maxAttempts {
			delete(s.records, clean)
			return false, "Terlalu banyak percobaan salah. Silakan minta kode baru."
		}
		return false, fmt.Sprintf("Kode OTP yang Anda masukkan salah (%d/%d percobaan).", record.Attempts, maxAttempts)
	}

	// Success: remove OTP
	delete(s.records, clean)
	return true, ""
}

func (s *Store) DeleteIfMatches(phone, purpose, code string) {
	clean := CleanPhoneNumber(phone)
	s.mu.Lock()
	defer s.mu.Unlock()
	codeHash := s.hashCode(clean, strings.TrimSpace(code))
	if record, ok := s.records[clean]; ok && record.Purpose == normalizePurpose(purpose) && hmac.Equal(record.CodeHash[:], codeHash[:]) {
		delete(s.records, clean)
	}
}

func (s *Store) hashCode(phone, code string) [32]byte {
	h := hmac.New(sha256.New, s.hashKey[:])
	_, _ = h.Write([]byte(phone))
	_, _ = h.Write([]byte{0})
	_, _ = h.Write([]byte(code))
	var result [32]byte
	copy(result[:], h.Sum(nil))
	return result
}

func normalizePurpose(purpose string) string {
	switch strings.ToLower(strings.TrimSpace(purpose)) {
	case "", "default", "verify", "verification":
		return "default"
	case "reset", "reset_password":
		return "reset"
	default:
		return "invalid"
	}
}

func isDigits(v string) bool {
	for _, r := range v {
		if r < '0' || r > '9' {
			return false
		}
	}
	return v != ""
}

// Background cleaner for expired OTP records
func (s *Store) startCleaner() {
	ticker := time.NewTicker(1 * time.Minute)
	for range ticker.C {
		s.mu.Lock()
		now := time.Now()
		for phone, rec := range s.records {
			if now.After(rec.ExpiresAt) {
				delete(s.records, phone)
			}
		}
		s.mu.Unlock()
	}
}
