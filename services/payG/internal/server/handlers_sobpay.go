package server

import (
	"encoding/json"
	"net/http"
	"strconv"

	"fastcargo/payg/internal/SoBpay/fees"
	"fastcargo/payg/internal/manager"
)

type SoBPayHandler struct {
	mgr *manager.Manager
}

func NewSoBPayHandler(mgr *manager.Manager) *SoBPayHandler {
	return &SoBPayHandler{mgr: mgr}
}

func (h *SoBPayHandler) Configure(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Username string `json:"username"`
	}

	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, `{"error":"invalid JSON"}`, http.StatusBadRequest)
		return
	}

	h.mgr.SoBPay().SetUsername(body.Username)
	_ = h.mgr.Store().SetSobpayUsername(body.Username)

	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"success":  true,
		"username": body.Username,
		"message":  "SociaBuzz creator username configured",
		"status":   h.mgr.SoBPay().Status(),
	})
}

func (h *SoBPayHandler) GetFees(w http.ResponseWriter, r *http.Request) {
	country := r.URL.Query().Get("country")
	if country == "" {
		country = "Indonesia"
	}

	amount := 100000.0
	if a := r.URL.Query().Get("amount"); a != "" {
		if parsed, err := strconv.ParseFloat(a, 64); err == nil && parsed > 0 {
			amount = parsed
		}
	}

	estimates := fees.CalculateFees(country, amount)
	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"success":   true,
		"country":   country,
		"amount":    amount,
		"estimates": estimates,
	})
}
