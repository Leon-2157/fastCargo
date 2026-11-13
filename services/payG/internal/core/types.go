package core

import "time"

// Supported engine identifiers
const (
	EngineAuto      = "auto"
	EngineSGPay     = "sgpay"
	EngineSoBPay    = "sobpay"
	EngineSimulator = "simulator"
)

// Payment status constants
const (
	StatusPending    = "PENDING"
	StatusSettlement = "SETTLEMENT"
	StatusExpired    = "EXPIRED"
	StatusFailed     = "FAILED"
)

// Supported SGpay sub-providers
const (
	ProviderShopee = "shopee"
	ProviderGoPay  = "gopay"
)

// Transaction represents an internal payment record tracked by PayG
type Transaction struct {
	ID             string         `json:"id"`
	TransactionRef string         `json:"transaction_ref"`
	InvoiceNumber  string         `json:"invoice_number"`
	Engine         string         `json:"engine"`
	Channel        string         `json:"channel"`
	BaseAmount     float64        `json:"base_amount"`
	UniqueCode     int64          `json:"unique_code"`
	PayableAmount  float64        `json:"payable_amount"`
	Currency       string         `json:"currency"`
	QRString       string         `json:"qr_string,omitempty"`
	QRSvg          string         `json:"qr_svg,omitempty"`
	PaymentURL     string         `json:"payment_url,omitempty"`
	Status         string         `json:"status"`
	MerchantName   string         `json:"merchant_name"`
	CustomerName   string         `json:"customer_name,omitempty"`
	CustomerEmail  string         `json:"customer_email,omitempty"`
	CustomerPhone  string         `json:"customer_phone,omitempty"`
	CreatedAt      time.Time      `json:"created_at"`
	ExpiresAt      time.Time      `json:"expires_at"`
	SettledAt      *time.Time     `json:"settled_at,omitempty"`
	RawDetails     map[string]any `json:"raw_details,omitempty"`
}

// CheckoutRequest is the payload sent from FastCargo Laravel to PayG
type CheckoutRequest struct {
	InvoiceNumber     string  `json:"invoice_number"`
	Amount            float64 `json:"amount"`
	Currency          string  `json:"currency"`
	CustomerName      string  `json:"customer_name"`
	CustomerEmail     string  `json:"customer_email"`
	CustomerPhone     string  `json:"customer_phone"`
	Country           string  `json:"country"`
	EnginePreference  string  `json:"engine_preference"`  // "sgpay", "sobpay", "simulator", "auto"
	ChannelPreference string  `json:"channel_preference"` // e.g. "qris", "va_bca", "gopay", "shopeepay"
	MerchantName      string  `json:"merchant_name"`
}

// CheckoutResponse returned back to FastCargo
type CheckoutResponse struct {
	TransactionRef string   `json:"transaction_ref"`
	InvoiceNumber  string   `json:"invoice_number"`
	Engine         string   `json:"engine"`
	Channel        string   `json:"channel"`
	Amount         float64  `json:"amount"`
	UniqueCode     int64    `json:"unique_code"`
	PayableAmount  float64  `json:"payable_amount"`
	Currency       string   `json:"currency"`
	QRString       string   `json:"qr_string,omitempty"`
	QRSvg          string   `json:"qr_svg,omitempty"`
	PaymentURL     string   `json:"payment_url,omitempty"`
	ExpiresAt      string   `json:"expires_at"`
	MerchantName   string   `json:"merchant_name"`
	Instructions   []string `json:"instructions,omitempty"`
	IsMock         bool     `json:"is_mock"`
}

// SettlementWebhookPayload is dispatched to FastCargo Laravel upon payment success
type SettlementWebhookPayload struct {
	Event          string         `json:"event"`
	TransactionRef string         `json:"transaction_ref"`
	InvoiceNumber  string         `json:"invoice_number"`
	Engine         string         `json:"engine"`
	Channel        string         `json:"channel"`
	Amount         float64        `json:"amount"`
	UniqueCode     int64          `json:"unique_code"`
	PayableAmount  float64        `json:"payable_amount"`
	Currency       string         `json:"currency"`
	Status         string         `json:"status"`
	SettledAt      string         `json:"settled_at,omitempty"`
	RawDetails     map[string]any `json:"raw_details,omitempty"`
}

// EngineStatus describes the health and readiness of PayG engines
type EngineStatus struct {
	Engine        string `json:"engine"`
	Active        bool   `json:"active"`
	Connected     bool   `json:"connected"`
	Details       string `json:"details"`
	ActiveAccount string `json:"active_account,omitempty"`
}

// SystemStatus is returned by GET /api/status
type SystemStatus struct {
	App           string                  `json:"app"`
	Version       string                  `json:"version"`
	ActiveMode    string                  `json:"active_mode"`
	Engines       map[string]EngineStatus `json:"engines"`
	ActivePending int                     `json:"active_pending"`
	TotalSettled  int                     `json:"total_settled"`
}
