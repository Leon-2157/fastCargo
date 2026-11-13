package simulator

import (
	"context"
	"fmt"
	"sync"
	"time"

	"fastcargo/payg/internal/SGpay/qris"
	"fastcargo/payg/internal/core"
)

type Engine struct {
	mu           sync.RWMutex
	activeOrders map[string]*core.Transaction
}

func NewEngine() *Engine {
	return &Engine{
		activeOrders: make(map[string]*core.Transaction),
	}
}

func (e *Engine) Status() core.EngineStatus {
	return core.EngineStatus{
		Engine:        core.EngineSimulator,
		Active:        true,
		Connected:     true,
		Details:       "Ready (Instant 1-Click Sandbox Testbed)",
		ActiveAccount: "SANDBOX-SIMULATOR",
	}
}

func (e *Engine) CreateCheckout(ctx context.Context, req core.CheckoutRequest) (*core.CheckoutResponse, error) {
	e.mu.Lock()
	defer e.mu.Unlock()

	ref := fmt.Sprintf("SIM-%s-%04d", time.Now().Format("20060102150405"), time.Now().Nanosecond()%10000)
	merchantName := req.MerchantName
	if merchantName == "" {
		merchantName = "FASTCARGO SIMULATOR"
	}

	qrPayload, err := qris.BuildSyntheticQRIS(merchantName, "JAKARTA", ref, int64(req.Amount))
	if err != nil {
		qrPayload = fmt.Sprintf("00020101021252045411530336054%02d%.2f5802ID5919FASTCARGO SIMULATOR6007JAKARTA62180714%s6304A1B2",
			len(fmt.Sprintf("%.2f", req.Amount)), req.Amount, ref)
	}

	qrSvg, _ := GenerateQRSvg(qrPayload, 280)

	now := time.Now()
	expiresAt := now.Add(30 * time.Minute)

	tx := &core.Transaction{
		ID:             ref,
		TransactionRef: ref,
		InvoiceNumber:  req.InvoiceNumber,
		Engine:         core.EngineSimulator,
		Channel:        "SIMULATOR_QRIS",
		BaseAmount:     req.Amount,
		UniqueCode:     0,
		PayableAmount:  req.Amount,
		Currency:       req.Currency,
		QRString:       qrPayload,
		QRSvg:          qrSvg,
		Status:         core.StatusPending,
		MerchantName:   merchantName,
		CustomerName:   req.CustomerName,
		CustomerEmail:  req.CustomerEmail,
		CustomerPhone:  req.CustomerPhone,
		CreatedAt:      now,
		ExpiresAt:      expiresAt,
	}
	if tx.Currency == "" {
		tx.Currency = "IDR"
	}

	e.activeOrders[ref] = tx

	instructions := []string{
		"Smart Simulator Mode: No real funds will be transferred.",
		"Click 'Simulate Instant Settlement' to trigger the signed webhook callback instantly.",
	}

	return &core.CheckoutResponse{
		TransactionRef: ref,
		InvoiceNumber:  req.InvoiceNumber,
		Engine:         core.EngineSimulator,
		Channel:        "QRIS",
		Amount:         req.Amount,
		UniqueCode:     0,
		PayableAmount:  req.Amount,
		Currency:       tx.Currency,
		QRString:       qrPayload,
		QRSvg:          qrSvg,
		ExpiresAt:      expiresAt.Format(time.RFC3339),
		MerchantName:   merchantName,
		Instructions:   instructions,
		IsMock:         true,
	}, nil
}

func (e *Engine) Settle(ref string) (*core.Transaction, error) {
	e.mu.Lock()
	defer e.mu.Unlock()

	tx, ok := e.activeOrders[ref]
	if !ok {
		return nil, core.NewAppErrorf(core.CodeTransactionNotFound, "Transaction %s not found in simulator", ref)
	}

	now := time.Now()
	tx.Status = core.StatusSettlement
	tx.SettledAt = &now

	return tx, nil
}
