package manager

import (
	"context"
	"log"
	"sync"
	"time"

	"fastcargo/payg/internal/SGpay"
	"fastcargo/payg/internal/SoBpay"
	"fastcargo/payg/internal/config"
	"fastcargo/payg/internal/core"
	"fastcargo/payg/internal/simulator"
	"fastcargo/payg/internal/store"
)

type Manager struct {
	mu         sync.RWMutex
	cfg        *config.Config
	store      *store.Store
	sgpay      *SGpay.Engine
	sobpay     *SoBpay.Engine
	simulator  *simulator.Engine
	dispatcher *WebhookDispatcher
	stopChan   chan struct{}
}

func NewManager(cfg *config.Config, s *store.Store) *Manager {
	sg := SGpay.NewEngine(s.GetSGPayStaticQR())
	if sess := s.GetShopeeSession(); sess.IsLoggedIn {
		sg.Shopee().SetSession(sess)
	}
	if sess := s.GetGoPaySession(); sess.IsLoggedIn {
		sg.GoPay().SetSession(sess)
	}

	sob := SoBpay.NewEngine(s.GetSobpayUsername())
	sim := simulator.NewEngine()
	disp := NewWebhookDispatcher(cfg.WebhookURL, cfg.SharedSecret)

	return &Manager{
		cfg:        cfg,
		store:      s,
		sgpay:      sg,
		sobpay:     sob,
		simulator:  sim,
		dispatcher: disp,
		stopChan:   make(chan struct{}),
	}
}

func (m *Manager) Store() *store.Store {
	return m.store
}

func (m *Manager) SGPay() *SGpay.Engine {
	return m.sgpay
}

func (m *Manager) SoBPay() *SoBpay.Engine {
	return m.sobpay
}

func (m *Manager) Simulator() *simulator.Engine {
	return m.simulator
}

func (m *Manager) Dispatcher() *WebhookDispatcher {
	return m.dispatcher
}

// GetSystemStatus gathers engine statuses and metrics
func (m *Manager) GetSystemStatus() core.SystemStatus {
	m.mu.RLock()
	defer m.mu.RUnlock()

	activeMode := m.store.GetActiveMode()
	engines := map[string]core.EngineStatus{
		core.EngineSGPay:     m.sgpay.Status(),
		core.EngineSoBPay:    m.sobpay.Status(),
		core.EngineSimulator: m.simulator.Status(),
	}

	txs := m.store.ListRecentTransactions(150)
	pending := 0
	settled := 0
	for _, tx := range txs {
		if tx.Status == core.StatusPending {
			pending++
		} else if tx.Status == core.StatusSettlement {
			settled++
		}
	}

	return core.SystemStatus{
		App:           m.cfg.AppName,
		Version:       "2.0.0",
		ActiveMode:    activeMode,
		Engines:       engines,
		ActivePending: pending,
		TotalSettled:  settled,
	}
}

// ResolveEngine decides which engine to route checkout to
func (m *Manager) ResolveEngine(pref string) string {
	if pref != "" && pref != core.EngineAuto {
		return pref
	}

	globalMode := m.store.GetActiveMode()
	if globalMode != "" && globalMode != core.EngineAuto {
		return globalMode
	}

	// Auto heuristics: SGpay connected > SoBpay connected > Simulator
	if m.sgpay.Status().Connected {
		return core.EngineSGPay
	}
	if m.sobpay.Status().Connected {
		return core.EngineSoBPay
	}
	return core.EngineSimulator
}

// CreateCheckout routes payment creation to the designated engine
func (m *Manager) CreateCheckout(ctx context.Context, req core.CheckoutRequest) (*core.CheckoutResponse, error) {
	chosenEngine := m.ResolveEngine(req.EnginePreference)

	var res *core.CheckoutResponse
	var err error

	switch chosenEngine {
	case core.EngineSGPay:
		res, err = m.sgpay.CreateCheckout(ctx, req)
	case core.EngineSoBPay:
		res, err = m.sobpay.CreateCheckout(ctx, req)
	default:
		chosenEngine = core.EngineSimulator
		res, err = m.simulator.CreateCheckout(ctx, req)
	}

	if err != nil {
		// Graceful fallback to Simulator if real gateway returns error
		log.Printf("[PayG] Engine %s failed checkout: %v. Falling back to Simulator.", chosenEngine, err)
		chosenEngine = core.EngineSimulator
		res, err = m.simulator.CreateCheckout(ctx, req)
		if err != nil {
			return nil, err
		}
	}

	// Generate QR SVG if QRString is present but QRSvg is empty
	if res.QRString != "" && res.QRSvg == "" {
		res.QRSvg, _ = simulator.GenerateQRSvg(res.QRString, 280)
	}

	// Persist transaction record
	now := time.Now()
	expiresAt, _ := time.Parse(time.RFC3339, res.ExpiresAt)
	tx := &core.Transaction{
		ID:             res.TransactionRef,
		TransactionRef: res.TransactionRef,
		InvoiceNumber:  res.InvoiceNumber,
		Engine:         res.Engine,
		Channel:        res.Channel,
		BaseAmount:     res.Amount,
		UniqueCode:     res.UniqueCode,
		PayableAmount:  res.PayableAmount,
		Currency:       res.Currency,
		QRString:       res.QRString,
		QRSvg:          res.QRSvg,
		PaymentURL:     res.PaymentURL,
		Status:         core.StatusPending,
		MerchantName:   res.MerchantName,
		CustomerName:   req.CustomerName,
		CustomerEmail:  req.CustomerEmail,
		CustomerPhone:  req.CustomerPhone,
		CreatedAt:      now,
		ExpiresAt:      expiresAt,
	}

	_ = m.store.RecordTransaction(tx)
	return res, nil
}

// SettleTransaction completes a transaction and dispatches signed webhook
func (m *Manager) SettleTransaction(ctx context.Context, ref string, rawDetails map[string]any) error {
	tx, ok := m.store.GetTransaction(ref)
	if !ok {
		return core.NewAppErrorf(core.CodeTransactionNotFound, "Transaction %s not found", ref)
	}

	if tx.Status == core.StatusSettlement {
		return nil
	}

	now := time.Now()
	tx.Status = core.StatusSettlement
	tx.SettledAt = &now
	if rawDetails != nil {
		if tx.RawDetails == nil {
			tx.RawDetails = make(map[string]any)
		}
		for k, v := range rawDetails {
			tx.RawDetails[k] = v
		}
	}

	// Release SGpay unique amount slot
	if tx.Engine == core.EngineSGPay {
		m.sgpay.ReleaseUniqueAmount(tx.PayableAmount)
	}

	_ = m.store.RecordTransaction(tx)

	// Send signed webhook callback to FastCargo
	payload := core.SettlementWebhookPayload{
		Event:          "payment.settled",
		TransactionRef: tx.TransactionRef,
		InvoiceNumber:  tx.InvoiceNumber,
		Engine:         tx.Engine,
		Channel:        tx.Channel,
		Amount:         tx.BaseAmount,
		UniqueCode:     tx.UniqueCode,
		PayableAmount:  tx.PayableAmount,
		Currency:       tx.Currency,
		Status:         core.StatusSettlement,
		SettledAt:      now.UTC().Format(time.RFC3339),
		RawDetails:     tx.RawDetails,
	}

	go func() {
		dispCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
		defer cancel()
		if err := m.dispatcher.DispatchSettlement(dispCtx, payload); err != nil {
			log.Printf("[PayG] Failed to dispatch webhook for %s: %v", ref, err)
		} else {
			log.Printf("[PayG] Webhook successfully delivered for %s", ref)
		}
	}()

	return nil
}

// FailTransaction marks a transaction with a failure/expired status and dispatches signed webhook
func (m *Manager) FailTransaction(ctx context.Context, ref string, status string, rawDetails map[string]any) error {
	tx, ok := m.store.GetTransaction(ref)
	if !ok {
		return core.NewAppErrorf(core.CodeTransactionNotFound, "Transaction %s not found", ref)
	}

	if tx.Status == core.StatusSettlement {
		return core.NewAppErrorf(core.CodeInvalidRequest, "Cannot fail an already settled transaction %s", ref)
	}

	tx.Status = status
	if rawDetails != nil {
		if tx.RawDetails == nil {
			tx.RawDetails = make(map[string]any)
		}
		for k, v := range rawDetails {
			tx.RawDetails[k] = v
		}
	}

	if tx.Engine == core.EngineSGPay {
		m.sgpay.ReleaseUniqueAmount(tx.PayableAmount)
	}

	_ = m.store.RecordTransaction(tx)

	event := "payment.failed"
	if status == core.StatusExpired {
		event = "payment.expired"
	} else if status == "CANCELLED" {
		event = "payment.cancelled"
	}

	payload := core.SettlementWebhookPayload{
		Event:          event,
		TransactionRef: tx.TransactionRef,
		InvoiceNumber:  tx.InvoiceNumber,
		Engine:         tx.Engine,
		Channel:        tx.Channel,
		Amount:         tx.BaseAmount,
		UniqueCode:     tx.UniqueCode,
		PayableAmount:  tx.PayableAmount,
		Currency:       tx.Currency,
		Status:         status,
		RawDetails:     tx.RawDetails,
	}

	go func() {
		dispCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
		defer cancel()
		if err := m.dispatcher.DispatchSettlement(dispCtx, payload); err != nil {
			log.Printf("[PayG] Failed to dispatch failure webhook for %s: %v", ref, err)
		} else {
			log.Printf("[PayG] Failure webhook successfully delivered for %s (%s)", ref, status)
		}
	}()

	return nil
}

// StartBackgroundPoller starts the mutation polling ticker
func (m *Manager) StartBackgroundPoller() {
	interval := time.Duration(m.cfg.PollIntervalSecond) * time.Second
	if interval < 5*time.Second {
		interval = 10 * time.Second
	}

	ticker := time.NewTicker(interval)
	go func() {
		for {
			select {
			case <-ticker.C:
				m.pollTick()
			case <-m.stopChan:
				ticker.Stop()
				return
			}
		}
	}()
}

func (m *Manager) pollTick() {
	ctx, cancel := context.WithTimeout(context.Background(), 8*time.Second)
	defer cancel()

	// 1. SoBpay status check
	settledSoB := m.sobpay.CheckPendingStatuses(ctx)
	for _, ref := range settledSoB {
		_ = m.SettleTransaction(ctx, ref, map[string]any{"source": "sobpay_poller"})
	}

	// 2. Clean expired orders in SGpay and SoBpay
	_ = m.sgpay.CleanExpiredOrders()
	_ = m.sobpay.CleanExpiredOrders()

	// 3. SGpay Shopee & GoPay mutation feeds
	// In production, matches mutations with e.takenAmounts and settles matches
}

func (m *Manager) Stop() {
	close(m.stopChan)
}
