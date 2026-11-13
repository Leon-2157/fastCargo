package fees

import (
	"testing"
)

func TestCalculateFeesIndonesia(t *testing.T) {
	estimates := CalculateFees("Indonesia", 10000000)
	if len(estimates) == 0 {
		t.Fatalf("expected fee estimates for Indonesia")
	}

	foundQRIS := false
	for _, est := range estimates {
		if est.Channel == "qris" {
			foundQRIS = true
			if est.EstimatedFee != 70000 {
				t.Fatalf("expected QRIS fee of 70.000 (0.7%%), got %f", est.EstimatedFee)
			}
		}
	}
	if !foundQRIS {
		t.Fatalf("expected QRIS estimate in results")
	}
}

func TestCalculateFeesMalaysia(t *testing.T) {
	estimates := CalculateFees("Malaysia", 500)
	if len(estimates) == 0 {
		t.Fatalf("expected estimates for Malaysia")
	}

	foundFPX := false
	for _, est := range estimates {
		if est.Channel == "fpx" {
			foundFPX = true
			if est.Currency != "MYR" {
				t.Fatalf("expected MYR currency, got %s", est.Currency)
			}
		}
	}
	if !foundFPX {
		t.Fatalf("expected FPX in Malaysia estimates")
	}
}
