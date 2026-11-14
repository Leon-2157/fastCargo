package SGpay

import (
	"context"
	"fmt"
	"strings"
	"sync"
	"time"

	"fastcargo/payg/internal/SGpay/allocator"
	"fastcargo/payg/internal/SGpay/gopay"
	"fastcargo/payg/internal/SGpay/qris"
	"fastcargo/payg/internal/SGpay/shopee"
	"fastcargo/payg/internal/core"
)

type Engine struct {
	mu           sync.RWMutex
	allocator    *allocator.Allocator
	shopeeClient *shopee.Client
	gopayClient  *gopay.Client
	staticQRIS   string
	activeOrders map[string]*core.Transaction // ref -> tx
	takenAmounts map[int64]bool               // payableAmount -> true
}

func NewEngine(staticQR string) *Engine {
	return &Engine{
		allocator:    allocator.NewAllocator(999),
		shopeeClient: shopee.NewClient(),
		gopayClient:  gopay.NewClient(),
		staticQRIS:   staticQR,
		activeOrders: make(map[string]*core.Transaction),
		takenAmounts: make(map[int64]bool),
	}
}

func (e *Engine) Shopee() *shopee.Client {
	return e.shopeeClient
}

func (e *Engine) GoPay() *gopay.Client {
	return e.gopayClient
}

func (e *Engine) SetStaticQR(qr string) {
	e.mu.Lock()
	defer e.mu.Unlock()
	e.staticQRIS = strings.TrimSpace(strings.ReplaceAll(strings.ReplaceAll(qr, "\r", ""), "\n", ""))
}

func (e *Engine) GetStaticQR() string {
	e.mu.RLock()
	defer e.mu.RUnlock()
	return e.staticQRIS
}

func (e *Engine) Status() core.EngineStatus {
	e.mu.RLock()
	defer e.mu.RUnlock()

	shopeeSess := e.shopeeClient.GetSession()
	gopaySess := e.gopayClient.GetSession()

	connected := shopeeSess.IsLoggedIn || gopaySess.IsLoggedIn
	details := "Offline (Awaiting Shopee/GoPay OTP login or Static QR)"
	activeAccount := ""

	if shopeeSess.IsLoggedIn {
		details = fmt.Sprintf("ShopeePay Connected (%s)", shopeeSess.PhoneNumber)
		activeAccount = shopeeSess.PhoneNumber
	} else if gopaySess.IsLoggedIn {
		details = fmt.Sprintf("GoPay Connected (%s)", gopaySess.PhoneNumber)
		activeAccount = gopaySess.PhoneNumber
	} else if e.staticQRIS != "" {
		details = "Static QRIS Injected Mode"
		connected = true
	}

	return core.EngineStatus{
		Engine:        core.EngineSGPay,
		Active:        true,
		Connected:     connected,
		Details:       details,
		ActiveAccount: activeAccount,
	}
}

// CreateCheckout generates an EMVCo dynamic QRIS with unique code
func (e *Engine) CreateCheckout(ctx context.Context, req core.CheckoutRequest) (*core.CheckoutResponse, error) {
	e.mu.Lock()
	defer e.mu.Unlock()

	baseAmount := int64(req.Amount)
	offset, err := e.allocator.Allocate(baseAmount, e.takenAmounts)
	if err != nil {
		return nil, err
	}

	payableAmount := baseAmount + offset
	ref := fmt.Sprintf("SGPAY-%s-%03d", time.Now().Format("20060102150405"), offset)
	merchantName := req.MerchantName
	if merchantName == "" {
		merchantName = "FASTCARGO ASEAN LOGISTICS"
	}

	var qrPayload string
	if e.staticQRIS != "" && qris.IsValidQRISChecksum(e.staticQRIS) {
		qrPayload, err = qris.StaticToDynamicQRIS(e.staticQRIS, payableAmount)
		if err != nil {
			return nil, fmt.Errorf("failed to inject Tag 54 into static QRIS: %w", err)
		}
	} else {
		qrPayload, err = qris.BuildSyntheticQRIS(merchantName, "JAKARTA", ref, payableAmount)
		if err != nil {
			return nil, fmt.Errorf("failed to build QRIS payload: %w", err)
		}
	}

	now := time.Now()
	expiresAt := now.Add(15 * time.Minute)

	tx := &core.Transaction{
		ID:             ref,
		TransactionRef: ref,
		InvoiceNumber:  req.InvoiceNumber,
		Engine:         core.EngineSGPay,
		Channel:        "QRIS_DYNAMIC",
		BaseAmount:     float64(baseAmount),
		UniqueCode:     offset,
		PayableAmount:  float64(payableAmount),
		Currency:       "IDR",
		QRString:       qrPayload,
		Status:         core.StatusPending,
		MerchantName:   merchantName,
		CustomerName:   req.CustomerName,
		CustomerEmail:  req.CustomerEmail,
		CustomerPhone:  req.CustomerPhone,
		CreatedAt:      now,
		ExpiresAt:      expiresAt,
	}

	e.activeOrders[ref] = tx
	e.takenAmounts[payableAmount] = true

	instructions := []string{
		fmt.Sprintf("Scan the dynamic QRIS with any e-wallet or banking app (ShopeePay, GoPay, BCA, Mandiri, etc.)"),
		fmt.Sprintf("Transfer EXACTLY Rp %s (including unique verification digits Rp %d)", formatNumber(payableAmount), offset),
		"Payment will be matched automatically and credited directly to merchant balance within 10 seconds.",
	}

	return &core.CheckoutResponse{
		TransactionRef: ref,
		InvoiceNumber:  req.InvoiceNumber,
		Engine:         core.EngineSGPay,
		Channel:        "QRIS",
		Amount:         float64(baseAmount),
		UniqueCode:     offset,
		PayableAmount:  float64(payableAmount),
		Currency:       "IDR",
		QRString:       qrPayload,
		ExpiresAt:      expiresAt.Format(time.RFC3339),
		MerchantName:   merchantName,
		Instructions:   instructions,
		IsMock:         false,
	}, nil
}

func (e *Engine) ReleaseUniqueAmount(payableAmount float64) {
	e.mu.Lock()
	defer e.mu.Unlock()
	delete(e.takenAmounts, int64(payableAmount))
}

// CleanExpiredOrders cleans pending orders past their expiration and frees taken amounts
func (e *Engine) CleanExpiredOrders() []string {
	e.mu.Lock()
	defer e.mu.Unlock()

	now := time.Now()
	var expiredRefs []string
	for ref, tx := range e.activeOrders {
		if tx.Status == core.StatusPending && now.After(tx.ExpiresAt) {
			tx.Status = core.StatusExpired
			delete(e.takenAmounts, int64(tx.PayableAmount))
			delete(e.activeOrders, ref)
			expiredRefs = append(expiredRefs, ref)
		}
	}
	return expiredRefs
}

func formatNumber(n int64) string {
	in := fmt.Sprintf("%d", n)
	out := ""
	for i, c := range in {
		if i > 0 && (len(in)-i)%3 == 0 {
			out += "."
		}
		out += string(c)
	}
	return out
}
