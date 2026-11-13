package SoBpay

import (
	"context"
	"fmt"
	"sync"
	"time"

	"fastcargo/payg/internal/SoBpay/client"
	"fastcargo/payg/internal/SoBpay/fees"
	"fastcargo/payg/internal/core"
)

type Engine struct {
	mu           sync.RWMutex
	username     string
	client       *client.HTTPClient
	activeOrders map[string]*core.Transaction // ref -> tx
}

func NewEngine(username string) *Engine {
	return &Engine{
		username:     username,
		client:       client.NewHTTPClient("https://sociabuzz.com"),
		activeOrders: make(map[string]*core.Transaction),
	}
}

func (e *Engine) SetUsername(u string) {
	e.mu.Lock()
	defer e.mu.Unlock()
	e.username = u
}

func (e *Engine) GetUsername() string {
	e.mu.RLock()
	defer e.mu.RUnlock()
	return e.username
}

func (e *Engine) Status() core.EngineStatus {
	e.mu.RLock()
	defer e.mu.RUnlock()

	connected := e.username != ""
	details := "Offline (SociaBuzz creator username not configured)"
	if connected {
		details = fmt.Sprintf("Connected (Creator @%s) - Multi-Channel & ASEAN active", e.username)
	}

	return core.EngineStatus{
		Engine:        core.EngineSoBPay,
		Active:        true,
		Connected:     connected,
		Details:       details,
		ActiveAccount: e.username,
	}
}

func (e *Engine) CreateCheckout(ctx context.Context, req core.CheckoutRequest) (*core.CheckoutResponse, error) {
	e.mu.Lock()
	defer e.mu.Unlock()

	uname := e.username
	if uname == "" {
		uname = "fastcargo"
	}

	country := req.Country
	if country == "" {
		country = "Indonesia"
	}

	curr := "IDR"
	if info, ok := fees.SupportedCountries[country]; ok {
		curr = info.Currency
	}

	res, err := e.client.SubmitDonation(ctx, uname, curr, req.Amount, req.CustomerName, req.CustomerEmail, req.InvoiceNumber)
	if err != nil {
		return nil, fmt.Errorf("failed to initiate SociaBuzz transaction: %w", err)
	}

	ref := fmt.Sprintf("SOBPAY-%s", res.Token)
	now := time.Now()
	expiresAt := now.Add(time.Duration(res.ExpiresIn) * time.Second)

	tx := &core.Transaction{
		ID:             ref,
		TransactionRef: ref,
		InvoiceNumber:  req.InvoiceNumber,
		Engine:         core.EngineSoBPay,
		Channel:        "SOBPAY_MULTICHANNEL",
		BaseAmount:     req.Amount,
		UniqueCode:     0, // SoBpay uses exact amount without odd code
		PayableAmount:  req.Amount,
		Currency:       curr,
		PaymentURL:     res.RedirectURL,
		Status:         core.StatusPending,
		MerchantName:   fmt.Sprintf("SociaBuzz (@%s)", uname),
		CustomerName:   req.CustomerName,
		CustomerEmail:  req.CustomerEmail,
		CustomerPhone:  req.CustomerPhone,
		CreatedAt:      now,
		ExpiresAt:      expiresAt,
		RawDetails: map[string]any{
			"token":        res.Token,
			"redirect_url": res.RedirectURL,
			"country":      country,
		},
	}

	e.activeOrders[ref] = tx

	instructions := []string{
		fmt.Sprintf("Click the payment link or scan the official Midtrans/Xendit QRIS on SociaBuzz."),
		"Pay the exact total nominal without additional unique digits.",
		"Supports Virtual Account (BCA, Mandiri, BRI, BNI), E-Wallets, and ASEAN gateways (FPX, PayNow).",
	}

	return &core.CheckoutResponse{
		TransactionRef: ref,
		InvoiceNumber:  req.InvoiceNumber,
		Engine:         core.EngineSoBPay,
		Channel:        "MULTI_CHANNEL",
		Amount:         req.Amount,
		UniqueCode:     0,
		PayableAmount:  req.Amount,
		Currency:       curr,
		PaymentURL:     res.RedirectURL,
		ExpiresAt:      expiresAt.Format(time.RFC3339),
		MerchantName:   fmt.Sprintf("FastCargo SociaBuzz Station (@%s)", uname),
		Instructions:   instructions,
		IsMock:         false,
	}, nil
}

func (e *Engine) CheckPendingStatuses(ctx context.Context) []string {
	e.mu.Lock()
	defer e.mu.Unlock()

	var settledRefs []string
	for ref, tx := range e.activeOrders {
		if tx.Status != core.StatusPending {
			continue
		}
		token := ""
		if t, ok := tx.RawDetails["token"].(string); ok {
			token = t
		}
		if token == "" {
			continue
		}

		status, err := e.client.CheckStatus(ctx, token)
		if err == nil && (status == "SUCCESS" || status == "SETTLEMENT" || status == "PAID") {
			tx.Status = core.StatusSettlement
			settledRefs = append(settledRefs, ref)
		}
	}
	return settledRefs
}

// CleanExpiredOrders removes orders past their expiration timestamp
func (e *Engine) CleanExpiredOrders() []string {
	e.mu.Lock()
	defer e.mu.Unlock()

	now := time.Now()
	var expiredRefs []string
	for ref, tx := range e.activeOrders {
		if tx.Status == core.StatusPending && now.After(tx.ExpiresAt) {
			tx.Status = core.StatusExpired
			delete(e.activeOrders, ref)
			expiredRefs = append(expiredRefs, ref)
		}
	}
	return expiredRefs
}
