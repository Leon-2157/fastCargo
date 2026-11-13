package simulator

import (
	"context"
	"strings"
	"testing"

	"fastcargo/payg/internal/core"
)

func TestGenerateQRSvg(t *testing.T) {
	svg, err := GenerateQRSvg("https://fastcargo.local/pay/123", 280)
	if err != nil {
		t.Fatalf("GenerateQRSvg failed: %v", err)
	}

	if !strings.HasPrefix(svg, "<svg") || !strings.HasSuffix(svg, "</svg>") {
		t.Fatalf("output is not valid SVG XML: %s", svg)
	}
}

func TestSimulatorEngine(t *testing.T) {
	engine := NewEngine()
	resp, err := engine.CreateCheckout(context.Background(), core.CheckoutRequest{
		InvoiceNumber: "INV-TEST-001",
		Amount:        250000,
		Currency:      "IDR",
	})
	if err != nil {
		t.Fatalf("CreateCheckout failed: %v", err)
	}

	if resp.TransactionRef == "" {
		t.Fatalf("missing transaction reference")
	}

	tx, err := engine.Settle(resp.TransactionRef)
	if err != nil {
		t.Fatalf("Settle failed: %v", err)
	}

	if tx.Status != core.StatusSettlement {
		t.Fatalf("expected SETTLEMENT status, got %s", tx.Status)
	}
}
