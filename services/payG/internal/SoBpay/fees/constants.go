package fees

type CountryInfo struct {
	Name     string `json:"name"`
	Currency string `json:"currency"`
	ISO2     string `json:"iso2"`
}

var SupportedCountries = map[string]CountryInfo{
	"Indonesia":   {Name: "Indonesia", Currency: "IDR", ISO2: "ID"},
	"Malaysia":    {Name: "Malaysia", Currency: "MYR", ISO2: "MY"},
	"Singapore":   {Name: "Singapore", Currency: "SGD", ISO2: "SG"},
	"Philippines": {Name: "Philippines", Currency: "PHP", ISO2: "PH"},
	"Thailand":    {Name: "Thailand", Currency: "THB", ISO2: "TH"},
	"Australia":   {Name: "Australia", Currency: "AUD", ISO2: "AU"},
	"Japan":       {Name: "Japan", Currency: "JPY", ISO2: "JP"},
	"United States": {Name: "United States", Currency: "USD", ISO2: "US"},
}

var DefaultFeeRates = map[string]map[string]float64{
	"IDR": {
		"qris":          0.007, // 0.7% (BI QRIS Standard)
		"bank_transfer": 4000,  // Rp 4.000 VA fee
		"ewallet":       0.015, // 1.5% E-Wallet
	},
	"MYR": {
		"fpx":     1.50, // RM 1.50 FPX
		"ewallet": 0.02, // 2% TnG / GrabPay
	},
	"SGD": {
		"paynow": 0.01, // 1% PayNow
	},
}
