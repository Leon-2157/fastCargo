package server

import (
	"encoding/json"
	"net/http"

	"fastcargo/payg/internal/SGpay/qris"
	"fastcargo/payg/internal/core"
	"fastcargo/payg/internal/manager"
)

type SGPayHandler struct {
	mgr *manager.Manager
}

func NewSGPayHandler(mgr *manager.Manager) *SGPayHandler {
	return &SGPayHandler{mgr: mgr}
}

func (h *SGPayHandler) RequestOTP(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Provider string `json:"provider"` // "shopee" or "gopay"
		Phone    string `json:"phone"`
	}

	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, `{"error":"invalid JSON"}`, http.StatusBadRequest)
		return
	}

	var challenge string
	var err error

	if body.Provider == core.ProviderGoPay {
		challenge, err = h.mgr.SGPay().GoPay().RequestOTP(r.Context(), body.Phone)
	} else {
		body.Provider = core.ProviderShopee
		challenge, err = h.mgr.SGPay().Shopee().RequestOTP(r.Context(), body.Phone)
	}

	if err != nil {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		_ = json.NewEncoder(w).Encode(map[string]any{
			"success": false,
			"error":   err.Error(),
		})
		return
	}

	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"success":   true,
		"provider":  body.Provider,
		"phone":     body.Phone,
		"challenge": challenge,
		"message":   "OTP challenge dispatched successfully",
	})
}

func (h *SGPayHandler) VerifyOTP(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Provider  string `json:"provider"`
		Phone     string `json:"phone"`
		OTP       string `json:"otp"`
		Challenge string `json:"challenge"`
	}

	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, `{"error":"invalid JSON"}`, http.StatusBadRequest)
		return
	}

	var ok bool
	var err error

	if body.Provider == core.ProviderGoPay {
		ok, err = h.mgr.SGPay().GoPay().VerifyOTP(r.Context(), body.Phone, body.OTP, body.Challenge)
		if ok && err == nil {
			_ = h.mgr.Store().SetGoPaySession(h.mgr.SGPay().GoPay().GetSession())
		}
	} else {
		body.Provider = core.ProviderShopee
		ok, err = h.mgr.SGPay().Shopee().VerifyOTP(r.Context(), body.Phone, body.OTP, body.Challenge)
		if ok && err == nil {
			_ = h.mgr.Store().SetShopeeSession(h.mgr.SGPay().Shopee().GetSession())
		}
	}

	if err != nil || !ok {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		errMsg := "Verification failed"
		if err != nil {
			errMsg = err.Error()
		}
		_ = json.NewEncoder(w).Encode(map[string]any{
			"success": false,
			"error":   errMsg,
		})
		return
	}

	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"success":  true,
		"provider": body.Provider,
		"message":  "Merchant account successfully paired and verified",
		"status":   h.mgr.SGPay().Status(),
	})
}

func (h *SGPayHandler) Disconnect(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Provider string `json:"provider"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body)

	if body.Provider == core.ProviderGoPay {
		h.mgr.SGPay().GoPay().Disconnect()
		_ = h.mgr.Store().SetGoPaySession(h.mgr.SGPay().GoPay().GetSession())
	} else if body.Provider == core.ProviderShopee {
		h.mgr.SGPay().Shopee().Disconnect()
		_ = h.mgr.Store().SetShopeeSession(h.mgr.SGPay().Shopee().GetSession())
	} else {
		h.mgr.SGPay().GoPay().Disconnect()
		h.mgr.SGPay().Shopee().Disconnect()
		_ = h.mgr.Store().SetGoPaySession(h.mgr.SGPay().GoPay().GetSession())
		_ = h.mgr.Store().SetShopeeSession(h.mgr.SGPay().Shopee().GetSession())
	}

	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"success": true,
		"message": "SGpay merchant sessions disconnected",
		"status":  h.mgr.SGPay().Status(),
	})
}

func (h *SGPayHandler) ConfigureStaticQR(w http.ResponseWriter, r *http.Request) {
	var body struct {
		StaticQR string `json:"static_qris"`
	}

	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, `{"error":"invalid JSON"}`, http.StatusBadRequest)
		return
	}

	if body.StaticQR != "" && !qris.IsValidQRISChecksum(body.StaticQR) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		_ = json.NewEncoder(w).Encode(map[string]any{
			"success": false,
			"error":   "Invalid QRIS checksum (CRC-16 tag 63 mismatch)",
		})
		return
	}

	h.mgr.SGPay().SetStaticQR(body.StaticQR)
	_ = h.mgr.Store().SetSGPayStaticQR(body.StaticQR)

	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"success": true,
		"message": "Static QRIS stored and validated successfully",
		"status":  h.mgr.SGPay().Status(),
	})
}
