package qris

import (
	"fmt"
	"strconv"

	"fastcargo/payg/internal/core"
)

// StaticToDynamicQRIS converts a static QRIS payload into a dynamic QRIS with Tag 54 nominal injection
func StaticToDynamicQRIS(staticPayload string, amount int64) (string, error) {
	if amount <= 0 {
		return "", core.NewAppError(core.CodeQRISParseError, "QRIS amount must be a positive integer")
	}

	if !IsValidQRISChecksum(staticPayload) {
		return "", core.NewAppError(core.CodeQRISParseError, "Static QRIS checksum invalid; cannot inject Tag 54")
	}

	m, err := ParseEmv(staticPayload)
	if err != nil {
		return "", err
	}

	if _, ok := m[TagPayloadFormat]; !ok {
		return "", core.NewAppError(core.CodeQRISParseError, "Missing Tag 00 in QRIS payload")
	}

	// Change Point-of-Initiation to dynamic (12)
	m[TagPointOfInitiation] = POIDynamic

	// Inject Tag 54 (Nominal)
	m[TagTransactionAmount] = strconv.FormatInt(amount, 10)

	// Ensure currency is IDR (360) and country is ID
	if _, ok := m[TagCurrencyCode]; !ok {
		m[TagCurrencyCode] = "360"
	}
	if _, ok := m[TagCountryCode]; !ok {
		m[TagCountryCode] = "ID"
	}

	return BuildEmv(m)
}

// BuildSyntheticQRIS builds an authentic EMVCo payload from scratch if merchant static QR is not provided
func BuildSyntheticQRIS(merchantName, city, ref string, amount int64) (string, error) {
	if merchantName == "" {
		merchantName = "FASTCARGO ASEAN LOGISTICS"
	}
	if city == "" {
		city = "JAKARTA"
	}

	m := EmvTlvMap{
		TagPayloadFormat:     "01",
		TagPointOfInitiation: POIDynamic,
		TagMerchantAccount26: "0014ID.DANA.WWW0118936009110022334455",
		TagMerchantAccount51: "0014ID.LINKAJA.WWW0118936009110022334455",
		TagMerchantCategory:  "5411",
		TagCurrencyCode:      "360",
		TagTransactionAmount: strconv.FormatInt(amount, 10),
		TagCountryCode:       "ID",
		TagMerchantName:      merchantName,
		TagMerchantCity:      city,
		TagAdditionalData62:  fmt.Sprintf("07%02d%s", len(ref), ref),
	}

	return BuildEmv(m)
}
