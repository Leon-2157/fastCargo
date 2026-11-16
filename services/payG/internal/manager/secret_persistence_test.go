package manager

import (
	"context"
	"testing"

	"fastcargo/payg/internal/SGpay/qris"
	"fastcargo/payg/internal/config"
	"fastcargo/payg/internal/core"
	"fastcargo/payg/internal/store"
)

func TestSharedSecretPersistsAcrossStoreAndManagerRestarts(t *testing.T) {
	dir := t.TempDir()

	st1, err := store.NewStore(dir)
	if err != nil {
		t.Fatalf("NewStore failed: %v", err)
	}

	cfg := &config.Config{
		AppName:            "FastCargo PayG Test",
		SharedSecret:       "initial-default-secret",
		WebhookURL:         "http://localhost/webhook",
		PollIntervalSecond: 10,
	}

	mgr1 := NewManager(cfg, st1)
	initialSig := mgr1.Dispatcher().ComputeSignature([]byte(`{"ping":1}`))

	if err := st1.SetSharedSecret("rotated-secret-2026"); err != nil {
		t.Fatalf("SetSharedSecret failed: %v", err)
	}

	// Simulate daemon restart with a fresh Store and Manager from the same storage dir
	st2, err := store.NewStore(dir)
	if err != nil {
		t.Fatalf("NewStore reload failed: %v", err)
	}

	if got := st2.GetSharedSecret(); got != "rotated-secret-2026" {
		t.Fatalf("expected persisted shared secret 'rotated-secret-2026', got %q", got)
	}

	mgr2 := NewManager(cfg, st2)
	reloadedSig := mgr2.Dispatcher().ComputeSignature([]byte(`{"ping":1}`))
	expectedDispatcher := NewWebhookDispatcher("http://localhost/webhook", "rotated-secret-2026")
	expectedSig := expectedDispatcher.ComputeSignature([]byte(`{"ping":1}`))

	if reloadedSig == initialSig {
		t.Fatalf("expected signature after reload to differ from initial default secret")
	}
	if reloadedSig != expectedSig {
		t.Fatalf("expected signature %s using persisted secret, got %s", expectedSig, reloadedSig)
	}
}

func TestSGPayCheckoutPreservesRealMerchantNameFromStaticQR(t *testing.T) {
	dir := t.TempDir()
	st, err := store.NewStore(dir)
	if err != nil {
		t.Fatalf("NewStore failed: %v", err)
	}

	staticQR, err := qris.BuildEmv(qris.EmvTlvMap{
		qris.TagPayloadFormat:     "01",
		qris.TagPointOfInitiation: qris.POIStatic,
		qris.TagMerchantAccount26: "0014COM.GO-JEK.WWW01159360091400001230208G1234567",
		qris.TagMerchantAccount51: "0014ID.CO.QRIS.WWW0215ID1020012345678",
		qris.TagMerchantCategory:  "5411",
		qris.TagCurrencyCode:      "360",
		qris.TagCountryCode:       "ID",
		qris.TagMerchantName:      "TOKO MAJU JAYA",
		qris.TagMerchantCity:      "JAKARTA SELATAN",
	})
	if err != nil {
		t.Fatalf("BuildEmv failed: %v", err)
	}
	if err := st.SetSGPayStaticQR(staticQR); err != nil {
		t.Fatalf("SetSGPayStaticQR failed: %v", err)
	}

	cfg := &config.Config{
		AppName:            "FastCargo PayG Test",
		SharedSecret:       "secret",
		WebhookURL:         "http://localhost/webhook",
		PollIntervalSecond: 10,
	}
	mgr := NewManager(cfg, st)

	res, err := mgr.CreateCheckout(context.Background(), core.CheckoutRequest{
		InvoiceNumber:    "INV-TEST-01",
		Amount:           150000,
		Currency:         "IDR",
		EnginePreference: core.EngineSGPay,
		MerchantName:     "FASTCARGO ASEAN LOGISTICS",
	})
	if err != nil {
		t.Fatalf("CreateCheckout failed: %v", err)
	}

	if res.MerchantName != "TOKO MAJU JAYA" {
		t.Fatalf("expected real MerchantName 'TOKO MAJU JAYA', got %q", res.MerchantName)
	}
	if !qris.IsValidQRISChecksum(res.QRString) {
		t.Fatalf("expected valid QRIS checksum on %s", res.QRString)
	}
}

func TestSGPayPendingUniqueAmountsSurviveRestart(t *testing.T) {
	dir := t.TempDir()
	st1, err := store.NewStore(dir)
	if err != nil {
		t.Fatalf("NewStore failed: %v", err)
	}

	cfg := &config.Config{
		AppName:            "FastCargo PayG Test",
		SharedSecret:       "secret",
		WebhookURL:         "http://localhost/webhook",
		PollIntervalSecond: 10,
	}

	mgr1 := NewManager(cfg, st1)
	res1, err := mgr1.CreateCheckout(context.Background(), core.CheckoutRequest{
		InvoiceNumber:    "INV-RESTART-01",
		Amount:           100000,
		Currency:         "IDR",
		EnginePreference: core.EngineSGPay,
	})
	if err != nil {
		t.Fatalf("CreateCheckout 1 failed: %v", err)
	}

	// Simulate daemon restart from same storage directory
	st2, err := store.NewStore(dir)
	if err != nil {
		t.Fatalf("NewStore reload failed: %v", err)
	}
	mgr2 := NewManager(cfg, st2)

	res2, err := mgr2.CreateCheckout(context.Background(), core.CheckoutRequest{
		InvoiceNumber:    "INV-RESTART-02",
		Amount:           100000,
		Currency:         "IDR",
		EnginePreference: core.EngineSGPay,
	})
	if err != nil {
		t.Fatalf("CreateCheckout 2 after restart failed: %v", err)
	}

	if int64(res2.PayableAmount) == int64(res1.PayableAmount) {
		t.Fatalf("expected restarted SGpay engine to avoid taken payable amount %.0f", res1.PayableAmount)
	}
}

func TestStoreCanClearSGPayStaticQR(t *testing.T) {
	dir := t.TempDir()
	st, err := store.NewStore(dir)
	if err != nil {
		t.Fatalf("NewStore failed: %v", err)
	}

	if err := st.SetSGPayStaticQR("0002010102116304ABCD"); err != nil {
		t.Fatalf("SetSGPayStaticQR set failed: %v", err)
	}
	if err := st.SetSGPayStaticQR(""); err != nil {
		t.Fatalf("SetSGPayStaticQR clear failed: %v", err)
	}

	stReload, err := store.NewStore(dir)
	if err != nil {
		t.Fatalf("NewStore reload failed: %v", err)
	}
	if got := stReload.GetSGPayStaticQR(); got != "" {
		t.Fatalf("expected cleared static QR '', got %q", got)
	}
}
