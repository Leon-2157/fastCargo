package dashboard

import (
	"crypto/rand"
	"encoding/hex"
	"fmt"
	"sync"
	"time"

	"golang.org/x/crypto/bcrypt"

	"wa-gateway/internal/config"
)

type AuthManager struct {
	mu            sync.RWMutex
	activeTokens  map[string]time.Time
	tokenDuration time.Duration
}

var (
	defaultAuth *AuthManager
	authOnce    sync.Once
)

func GetAuth() *AuthManager {
	authOnce.Do(func() {
		defaultAuth = &AuthManager{
			activeTokens:  make(map[string]time.Time),
			tokenDuration: 7 * 24 * time.Hour, // 7 days session
		}
	})
	return defaultAuth
}

// VerifyPassword checks input password against hash or default password
func (a *AuthManager) VerifyPassword(input string) bool {
	cfg := config.Get().Snapshot()
	hash := cfg.AdminPasswordHash

	if hash == "" {
		return false
	}

	err := bcrypt.CompareHashAndPassword([]byte(hash), []byte(input))
	return err == nil
}

// ChangePassword verifies old password and updates to new password
func (a *AuthManager) ChangePassword(oldPass, newPass string) error {
	if !a.VerifyPassword(oldPass) {
		return fmt.Errorf("kata sandi lama salah")
	}

	if len([]rune(newPass)) < 12 || len(newPass) > 72 {
		return fmt.Errorf("kata sandi baru minimal 12 karakter dan maksimal 72 byte UTF-8")
	}

	hashBytes, err := bcrypt.GenerateFromPassword([]byte(newPass), bcrypt.DefaultCost)
	if err != nil {
		return fmt.Errorf("gagal mengenkripsi kata sandi: %w", err)
	}

	if err := config.Get().UpdateAndPersist(map[string]string{
		"ADMIN_PASSWORD_HASH": string(hashBytes),
	}); err != nil {
		return err
	}
	a.RevokeAllSessions()
	return nil
}

// CreateSession generates a new session token for dashboard access
func (a *AuthManager) CreateSession() (string, error) {
	a.mu.Lock()
	defer a.mu.Unlock()
	now := time.Now()
	for existing, expiry := range a.activeTokens {
		if !expiry.After(now) {
			delete(a.activeTokens, existing)
		}
	}
	if len(a.activeTokens) >= 10000 {
		return "", fmt.Errorf("batas sesi dashboard tercapai")
	}

	b := make([]byte, 24)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	token := hex.EncodeToString(b)
	a.activeTokens[token] = time.Now().Add(a.tokenDuration)
	return token, nil
}

func (a *AuthManager) RevokeAllSessions() {
	a.mu.Lock()
	defer a.mu.Unlock()
	a.activeTokens = make(map[string]time.Time)
}

// ValidateSession verifies token validity
func (a *AuthManager) ValidateSession(token string) bool {
	if token == "" {
		return false
	}

	a.mu.Lock()
	defer a.mu.Unlock()

	exp, found := a.activeTokens[token]
	if !found {
		return false
	}

	if time.Now().After(exp) {
		delete(a.activeTokens, token)
		return false
	}
	return true
}

// RevokeSession logs out the token
func (a *AuthManager) RevokeSession(token string) {
	a.mu.Lock()
	defer a.mu.Unlock()
	delete(a.activeTokens, token)
}
