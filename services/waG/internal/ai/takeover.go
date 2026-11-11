package ai

import (
	"strings"
	"sync"
	"time"
)

type TakeoverManager struct {
	mu     sync.RWMutex
	paused map[string]time.Time
}

var (
	defaultTakeover *TakeoverManager
	takeoverOnce    sync.Once
)

func GetTakeover() *TakeoverManager {
	takeoverOnce.Do(func() {
		defaultTakeover = &TakeoverManager{
			paused: make(map[string]time.Time),
		}
	})
	return defaultTakeover
}

// RecordAdminReply records that human admin replied manually from phone, muting AI for duration
func (t *TakeoverManager) RecordAdminReply(phone string, durationMinutes int) {
	phone = strings.TrimSpace(phone)
	if phone == "" {
		return
	}
	if durationMinutes < 1 {
		durationMinutes = 1
	}
	if durationMinutes > 180 {
		durationMinutes = 180
	}
	t.mu.Lock()
	defer t.mu.Unlock()
	now := time.Now()
	for existing, until := range t.paused {
		if !until.After(now) {
			delete(t.paused, existing)
		}
	}
	if _, exists := t.paused[phone]; !exists && len(t.paused) >= 10000 {
		return
	}

	until := now.Add(time.Duration(durationMinutes) * time.Minute)
	t.paused[phone] = until
}

// IsPaused checks if AI should remain silent for this phone
func (t *TakeoverManager) IsPaused(phone string) bool {
	t.mu.Lock()
	defer t.mu.Unlock()

	until, exists := t.paused[phone]
	if !exists {
		return false
	}

	if time.Now().After(until) {
		delete(t.paused, phone)
		return false
	}
	return true
}

// Resume unmutes AI for the phone immediately
func (t *TakeoverManager) Resume(phone string) {
	t.mu.Lock()
	defer t.mu.Unlock()
	delete(t.paused, phone)
}

// GetActiveTakeovers returns list of currently muted phones and remaining seconds
type TakeoverStatus struct {
	Phone            string `json:"phone"`
	RemainingMinutes int    `json:"remaining_minutes"`
}

func (t *TakeoverManager) GetActiveTakeovers() []TakeoverStatus {
	t.mu.RLock()
	defer t.mu.RUnlock()

	now := time.Now()
	var list []TakeoverStatus
	for phone, until := range t.paused {
		if until.After(now) {
			list = append(list, TakeoverStatus{
				Phone:            phone,
				RemainingMinutes: int(until.Sub(now).Minutes()) + 1,
			})
		}
	}
	return list
}
