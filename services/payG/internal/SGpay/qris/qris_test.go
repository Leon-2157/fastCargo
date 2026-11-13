package qris

import (
	"strings"
	"testing"
)

func TestCRC16CCITT(t *testing.T) {
	// Standard EMVCo test case
	sample := "00020101021226580014ID.DANA.WWW011893600911002233445551440014ID.LINKAJA.WWW01189360091100223344555204541153033605802ID5925FASTCARGO ASEAN LOGISTICS6007JAKARTA6304"
	crc := CRC16CCITT(sample)
	if len(crc) != 4 {
		t.Fatalf("expected 4-char CRC, got %s", crc)
	}
	full := sample + crc
	if !IsValidQRISChecksum(full) {
		t.Fatalf("expected checksum to validate on %s", full)
	}
}

func TestStaticToDynamicQRIS(t *testing.T) {
	staticQR := "00020101021126580014ID.DANA.WWW01189360091100223344555204541153033605802ID5925FASTCARGO ASEAN LOGISTICS6007JAKARTA6304"
	crc := CRC16CCITT(staticQR)
	fullStatic := staticQR + crc

	dynamicQR, err := StaticToDynamicQRIS(fullStatic, 150312)
	if err != nil {
		t.Fatalf("StaticToDynamicQRIS failed: %v", err)
	}

	if !IsValidQRISChecksum(dynamicQR) {
		t.Fatalf("dynamic QR has invalid checksum: %s", dynamicQR)
	}

	if !strings.Contains(dynamicQR, "5406150312") {
		t.Fatalf("expected Tag 54 nominal 150312 in payload: %s", dynamicQR)
	}

	if !strings.Contains(dynamicQR, "010212") {
		t.Fatalf("expected Point of Initiation to flip to dynamic (12): %s", dynamicQR)
	}
}

func TestBuildSyntheticQRIS(t *testing.T) {
	qris, err := BuildSyntheticQRIS("FASTCARGO LOGISTICS", "JAKARTA", "TX-12345", 50000)
	if err != nil {
		t.Fatalf("BuildSyntheticQRIS failed: %v", err)
	}

	if !IsValidQRISChecksum(qris) {
		t.Fatalf("checksum verification failed for synthetic QRIS: %s", qris)
	}
}
