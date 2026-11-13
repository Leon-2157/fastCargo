package SGpay

import (
	"context"
	"testing"
	"time"

	"fastcargo/payg/internal/core"
)

func TestCleanExpiredOrders(t *testing.T) {
	engine := NewEngine("")

	req := core.CheckoutRequest{
		InvoiceNumber: "INV-EXP-001",
		Amount:        100000,
		CustomerName:  "Test Shipper",
	}

	checkout, err := engine.CreateCheckout(context.Background(), req)
	if err != nil {
		t.Fatalf("CreateCheckout failed: %v", err)
	}

	engine.mu.Lock()
	tx := engine.activeOrders[checkout.TransactionRef]
	// Manually set expiration to the past
	tx.ExpiresAt = time.Now().Add(-5 * time.Minute)
	engine.mu.Unlock()

	cleaned := engine.CleanExpiredOrders()
	if len(cleaned) != 1 || cleaned[0] != checkout.TransactionRef {
		t.Fatalf("expected 1 cleaned ref %s, got %v", checkout.TransactionRef, cleaned)
	}

	// Verify unique amount slot is released
	engine.mu.RLock()
	isTaken := engine.takenAmounts[int64(checkout.PayableAmount)]
	engine.mu.RUnlock()

	if isTaken {
		t.Fatalf("expected payable amount %f to be released from takenAmounts", checkout.PayableAmount)
	}
}
