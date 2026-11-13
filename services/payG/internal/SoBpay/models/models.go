package models

type PaymentMethod struct {
	ID          string  `json:"id"`
	Name        string  `json:"name"`
	Label       string  `json:"label"`
	Group       string  `json:"group"`
	Gateway     string  `json:"gateway"`
	FeePercent  float64 `json:"fee_percent"`
	FeeFixed    float64 `json:"fee_fixed"`
	Description string  `json:"description,omitempty"`
}

type DonationResult struct {
	Success      bool    `json:"success"`
	Token        string  `json:"token"`
	RedirectURL  string  `json:"redirect_url"`
	ActualAmount float64 `json:"actual_amount"`
	Currency     string  `json:"currency"`
	ExpiresIn    int     `json:"expires_in"`
}

type PaymentResult struct {
	PaymentURL string `json:"payment_url"`
	QRString   string `json:"qr_string,omitempty"`
	VANumber   string `json:"va_number,omitempty"`
	BankName   string `json:"bank_name,omitempty"`
	Status     string `json:"status"`
}
