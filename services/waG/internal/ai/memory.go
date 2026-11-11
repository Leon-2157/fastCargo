package ai

import (
	"sync"
	"time"
	"unicode/utf8"
)

type ChatMessage struct {
	Role      string    `json:"role"` // "user" | "assistant" | "system"
	Content   string    `json:"content"`
	Timestamp time.Time `json:"timestamp"`
}

const maxSessions = 10000

type UserSession struct {
	Messages  []ChatMessage
	LastSeen  time.Time
	ExpiresAt time.Time
}

type MemoryStore struct {
	mu       sync.RWMutex
	sessions map[string]*UserSession
}

var (
	defaultMemory *MemoryStore
	memOnce       sync.Once
)

func GetMemory() *MemoryStore {
	memOnce.Do(func() {
		defaultMemory = &MemoryStore{
			sessions: make(map[string]*UserSession),
		}
		go defaultMemory.cleaner()
	})
	return defaultMemory
}

func (m *MemoryStore) AddMessage(phone, role, content string, maxTurns int, ttlMin int) {
	if maxTurns < 1 {
		maxTurns = 8
	}
	if maxTurns > 20 {
		maxTurns = 20
	}
	if ttlMin < 1 {
		ttlMin = 30
	}
	if utf8.RuneCountInString(content) > 4000 {
		content = string([]rune(content)[:4000])
	}
	m.mu.Lock()
	defer m.mu.Unlock()

	now := time.Now()
	sess, found := m.sessions[phone]
	if !found || !sess.ExpiresAt.After(now) {
		if !found && len(m.sessions) >= maxSessions {
			var oldestPhone string
			var oldest time.Time
			for existingPhone, existing := range m.sessions {
				if !existing.ExpiresAt.After(now) {
					delete(m.sessions, existingPhone)
					continue
				}
				if oldestPhone == "" || existing.LastSeen.Before(oldest) {
					oldestPhone, oldest = existingPhone, existing.LastSeen
				}
			}
			if len(m.sessions) >= maxSessions && oldestPhone != "" {
				delete(m.sessions, oldestPhone)
			}
		}
		sess = &UserSession{
			Messages: make([]ChatMessage, 0, maxTurns),
		}
		m.sessions[phone] = sess
	}

	sess.LastSeen = now
	sess.ExpiresAt = now.Add(time.Duration(ttlMin) * time.Minute)
	sess.Messages = append(sess.Messages, ChatMessage{
		Role:      role,
		Content:   content,
		Timestamp: now,
	})

	// Keep within max turns
	if len(sess.Messages) > maxTurns {
		sess.Messages = sess.Messages[len(sess.Messages)-maxTurns:]
	}
}

func (m *MemoryStore) GetHistory(phone string, ttlMin int) []ChatMessage {
	m.mu.RLock()
	defer m.mu.RUnlock()

	sess, found := m.sessions[phone]
	if !found {
		return nil
	}

	if !sess.ExpiresAt.After(time.Now()) {
		return nil
	}

	res := make([]ChatMessage, len(sess.Messages))
	copy(res, sess.Messages)
	return res
}

func (m *MemoryStore) ResetHistory(phone string) {
	m.mu.Lock()
	defer m.mu.Unlock()
	delete(m.sessions, phone)
}

func (m *MemoryStore) cleaner() {
	ticker := time.NewTicker(5 * time.Minute)
	for range ticker.C {
		m.mu.Lock()
		now := time.Now()
		for phone, sess := range m.sessions {
			if !sess.ExpiresAt.After(now) {
				delete(m.sessions, phone)
			}
		}
		m.mu.Unlock()
	}
}
