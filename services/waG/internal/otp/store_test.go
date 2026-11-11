package otp

import (
	"strings"
	"sync"
	"sync/atomic"
	"testing"
)

func newTestStore() *Store {
	return &Store{records: make(map[string]*OtpRecord), hashKey: [32]byte{1, 2, 3, 4}}
}

func TestConcurrentGenerationEnforcesOneCooldown(t *testing.T) {
	s := newTestStore()
	var successes atomic.Int32
	var wg sync.WaitGroup
	for i := 0; i < 25; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			if _, err := s.Generate("6281234567890", 5, 60); err == nil {
				successes.Add(1)
			}
		}()
	}
	wg.Wait()
	if got := successes.Load(); got != 1 {
		t.Fatalf("successful sends=%d, want one", got)
	}
}

func TestCleanPhoneNumberIndonesianMobile(t *testing.T) {
	tests := []struct{ input, want string }{
		{"+62 812-3456-7890", "6281234567890"},
		{"0812 3456 7890", "6281234567890"},
		{"81234567890", "6281234567890"},
		{"6281234567890", "6281234567890"},
		{"0215550123", ""},
		{"08123", ""},
		{"", ""},
	}
	for _, tt := range tests {
		t.Run(tt.input, func(t *testing.T) {
			if got := CleanPhoneNumber(tt.input); got != tt.want {
				t.Fatalf("CleanPhoneNumber(%q)=%q, want %q", tt.input, got, tt.want)
			}
		})
	}
}

func TestOTPIsPurposeBoundAndSingleUse(t *testing.T) {
	s := newTestStore()
	phone := "6281234567890"
	code, err := s.GenerateForPurpose(phone, "reset_password", 5, 60)
	if err != nil {
		t.Fatal(err)
	}
	if ok, _ := s.VerifyForPurpose(phone, "default", code, 5); ok {
		t.Fatal("OTP verified for a different purpose")
	}
	if _, exists := s.records[phone]; !exists {
		t.Fatal("purpose mismatch consumed the OTP")
	}
	if ok, reason := s.VerifyForPurpose(phone, "reset", code, 5); !ok {
		t.Fatalf("valid OTP rejected: %s", reason)
	}
	if ok, _ := s.VerifyForPurpose(phone, "reset", code, 5); ok {
		t.Fatal("OTP was reusable")
	}
}

func TestOTPAttemptLimitAndCooldown(t *testing.T) {
	s := newTestStore()
	phone := "6281234567890"
	code, err := s.Generate(phone, 5, 60)
	if err != nil {
		t.Fatal(err)
	}
	if _, err := s.Generate(phone, 5, 60); err == nil {
		t.Fatal("cooldown did not reject a repeat send")
	}
	if ok, _ := s.Verify(phone, "000000", 2); ok {
		t.Fatal("incorrect OTP verified")
	}
	if ok, reason := s.Verify(phone, "000000", 2); ok || !strings.Contains(reason, "Terlalu banyak") {
		t.Fatalf("expected attempt-limit failure, got %q", reason)
	}
	if _, exists := s.records[phone]; exists {
		t.Fatal("OTP remained after attempt limit")
	}
	if ok, _ := s.Verify(phone, code, 2); ok {
		t.Fatal("OTP verified after attempt limit")
	}
}

func TestDeleteIfMatchesCannotRemoveDifferentCode(t *testing.T) {
	s := newTestStore()
	phone := "6281234567890"
	code, err := s.Generate(phone, 5, 60)
	if err != nil {
		t.Fatal(err)
	}
	s.DeleteIfMatches(phone, "default", "000000")
	if _, exists := s.records[phone]; !exists {
		t.Fatal("different code removed active OTP")
	}
	s.DeleteIfMatches(phone, "default", code)
	if _, exists := s.records[phone]; exists {
		t.Fatal("matching code did not remove OTP")
	}
}
