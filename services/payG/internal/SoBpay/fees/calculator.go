package fees

import (
	"math"
)

type FeeEstimate struct {
	MethodName  string  `json:"method_name"`
	Channel     string  `json:"channel"`
	FeePercent  float64 `json:"fee_percent"`
	FeeFixed    float64 `json:"fee_fixed"`
	EstimatedFee float64 `json:"estimated_fee"`
	TotalAmount float64 `json:"total_amount"`
	Currency    string  `json:"currency"`
}

func CalculateFees(country string, amount float64) []FeeEstimate {
	info, ok := SupportedCountries[country]
	if !ok {
		info = SupportedCountries["Indonesia"]
	}

	estimates := make([]FeeEstimate, 0)
	switch info.Currency {
	case "IDR":
		// QRIS
		qrisFee := math.Round(amount * 0.007)
		estimates = append(estimates, FeeEstimate{
			MethodName:   "QRIS (Xendit / Midtrans)",
			Channel:      "qris",
			FeePercent:   0.7,
			FeeFixed:     0,
			EstimatedFee: qrisFee,
			TotalAmount:  amount + qrisFee,
			Currency:     "IDR",
		})
		// Virtual Account (BCA, Mandiri, BRI, BNI)
		vaFee := 4000.0
		estimates = append(estimates, FeeEstimate{
			MethodName:   "Virtual Account (BCA, Mandiri, BNI, BRI)",
			Channel:      "bank_transfer",
			FeePercent:   0,
			FeeFixed:     vaFee,
			EstimatedFee: vaFee,
			TotalAmount:  amount + vaFee,
			Currency:     "IDR",
		})
		// E-Wallet (OVO, DANA, GoPay, ShopeePay)
		ewalletFee := math.Round(amount * 0.015)
		estimates = append(estimates, FeeEstimate{
			MethodName:   "E-Wallet (GoPay, OVO, DANA, ShopeePay)",
			Channel:      "ewallet",
			FeePercent:   1.5,
			FeeFixed:     0,
			EstimatedFee: ewalletFee,
			TotalAmount:  amount + ewalletFee,
			Currency:     "IDR",
		})

	case "MYR":
		estimates = append(estimates, FeeEstimate{
			MethodName:   "Malaysia Online Banking (FPX)",
			Channel:      "fpx",
			FeePercent:   0,
			FeeFixed:     1.50,
			EstimatedFee: 1.50,
			TotalAmount:  amount + 1.50,
			Currency:     "MYR",
		})
		estimates = append(estimates, FeeEstimate{
			MethodName:   "Touch 'n Go eWallet / GrabPay",
			Channel:      "ewallet",
			FeePercent:   2.0,
			FeeFixed:     0,
			EstimatedFee: math.Round(amount*0.02*100) / 100,
			TotalAmount:  math.Round((amount+(amount*0.02))*100) / 100,
			Currency:     "MYR",
		})

	case "SGD":
		estimates = append(estimates, FeeEstimate{
			MethodName:   "Singapore PayNow QR",
			Channel:      "paynow",
			FeePercent:   1.0,
			FeeFixed:     0,
			EstimatedFee: math.Round(amount*0.01*100) / 100,
			TotalAmount:  math.Round((amount+(amount*0.01))*100) / 100,
			Currency:     "SGD",
		})

	default:
		estimates = append(estimates, FeeEstimate{
			MethodName:   "International Credit / Debit Card (Stripe)",
			Channel:      "credit_card",
			FeePercent:   3.5,
			FeeFixed:     0.50,
			EstimatedFee: math.Round((amount*0.035+0.50)*100) / 100,
			TotalAmount:  math.Round((amount+(amount*0.035+0.50))*100) / 100,
			Currency:     info.Currency,
		})
	}

	return estimates
}
