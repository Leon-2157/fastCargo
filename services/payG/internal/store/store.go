package store

import (
	"encoding/json"
	"os"
	"path/filepath"
	"sync"
	"time"

	"fastcargo/payg/internal/SGpay/gopay"
	"fastcargo/payg/internal/SGpay/shopee"
	"fastcargo/payg/internal/core"
)

type StateData struct {
	ActiveMode     string                `json:"active_mode"`
	SgpayStaticQR  string                `json:"sgpay_static_qr"`
	ShopeeSession  shopee.SessionInfo    `json:"shopee_session"`
	GoPaySession   gopay.SessionInfo     `json:"gopay_session"`
	SobpayUsername string                `json:"sobpay_username"`
	Transactions   []*core.Transaction   `json:"transactions"`
	UpdatedAt      time.Time             `json:"updated_at"`
}

type Store struct {
	mu       sync.RWMutex
	filePath string
	data     StateData
}

func NewStore(dirPath string) (*Store, error) {
	if err := os.MkdirAll(dirPath, 0755); err != nil {
		return nil, err
	}

	filePath := filepath.Join(dirPath, "payg_state.json")
	s := &Store{
		filePath: filePath,
		data: StateData{
			ActiveMode:   core.EngineAuto,
			Transactions: make([]*core.Transaction, 0),
			UpdatedAt:    time.Now(),
		},
	}

	_ = s.load()
	return s, nil
}

func (s *Store) load() error {
	raw, err := os.ReadFile(s.filePath)
	if err != nil {
		return err
	}
	var loaded StateData
	if err := json.Unmarshal(raw, &loaded); err != nil {
		return err
	}
	s.data = loaded
	return nil
}

func (s *Store) saveLocked() error {
	s.data.UpdatedAt = time.Now()
	// Cap transactions at 150 items
	if len(s.data.Transactions) > 150 {
		s.data.Transactions = s.data.Transactions[len(s.data.Transactions)-150:]
	}

	bytes, err := json.MarshalIndent(s.data, "", "  ")
	if err != nil {
		return err
	}

	tmpFile := s.filePath + ".tmp"
	if err := os.WriteFile(tmpFile, bytes, 0644); err != nil {
		return err
	}
	return os.Rename(tmpFile, s.filePath)
}

func (s *Store) GetActiveMode() string {
	s.mu.RLock()
	defer s.mu.RUnlock()
	if s.data.ActiveMode == "" {
		return core.EngineAuto
	}
	return s.data.ActiveMode
}

func (s *Store) SetActiveMode(m string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.data.ActiveMode = m
	return s.saveLocked()
}

func (s *Store) GetSGPayStaticQR() string {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.data.SgpayStaticQR
}

func (s *Store) SetSGPayStaticQR(qr string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.data.SgpayStaticQR = qr
	return s.saveLocked()
}

func (s *Store) GetSobpayUsername() string {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.data.SobpayUsername
}

func (s *Store) SetSobpayUsername(u string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.data.SobpayUsername = u
	return s.saveLocked()
}

func (s *Store) GetShopeeSession() shopee.SessionInfo {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.data.ShopeeSession
}

func (s *Store) SetShopeeSession(sess shopee.SessionInfo) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.data.ShopeeSession = sess
	return s.saveLocked()
}

func (s *Store) GetGoPaySession() gopay.SessionInfo {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.data.GoPaySession
}

func (s *Store) SetGoPaySession(sess gopay.SessionInfo) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.data.GoPaySession = sess
	return s.saveLocked()
}

func (s *Store) RecordTransaction(tx *core.Transaction) error {
	s.mu.Lock()
	defer s.mu.Unlock()

	// Update existing or append
	found := false
	for i, existing := range s.data.Transactions {
		if existing.TransactionRef == tx.TransactionRef {
			s.data.Transactions[i] = tx
			found = true
			break
		}
	}
	if !found {
		s.data.Transactions = append(s.data.Transactions, tx)
	}

	return s.saveLocked()
}

func (s *Store) GetTransaction(ref string) (*core.Transaction, bool) {
	s.mu.RLock()
	defer s.mu.RUnlock()

	for _, tx := range s.data.Transactions {
		if tx.TransactionRef == ref {
			return tx, true
		}
	}
	return nil, false
}

func (s *Store) ListRecentTransactions(limit int) []*core.Transaction {
	s.mu.RLock()
	defer s.mu.RUnlock()

	total := len(s.data.Transactions)
	if limit <= 0 || limit > total {
		limit = total
	}

	res := make([]*core.Transaction, limit)
	for i := 0; i < limit; i++ {
		res[i] = s.data.Transactions[total-1-i]
	}
	return res
}
