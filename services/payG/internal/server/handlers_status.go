package server

import (
	"encoding/json"
	"net/http"
	"strconv"
	"strings"

	"fastcargo/payg/internal/manager"
)

type StatusHandler struct {
	mgr *manager.Manager
}

func NewStatusHandler(mgr *manager.Manager) *StatusHandler {
	return &StatusHandler{mgr: mgr}
}

func (h *StatusHandler) Health(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	_ = json.NewEncoder(w).Encode(map[string]any{
		"status": "ok",
		"app":    "FastCargo PayG Station",
	})
}

func (h *StatusHandler) GetStatus(w http.ResponseWriter, r *http.Request) {
	status := h.mgr.GetSystemStatus()
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	_ = json.NewEncoder(w).Encode(status)
}

func (h *StatusHandler) GetTransactions(w http.ResponseWriter, r *http.Request) {
	limit := 20
	if l := r.URL.Query().Get("limit"); l != "" {
		if parsed, err := strconv.Atoi(l); err == nil && parsed > 0 {
			limit = parsed
		}
	}

	txs := h.mgr.Store().ListRecentTransactions(limit)
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	_ = json.NewEncoder(w).Encode(map[string]any{
		"success":      true,
		"transactions": txs,
	})
}

func (h *StatusHandler) UpdateConfig(w http.ResponseWriter, r *http.Request) {
	var body struct {
		ActiveMode     string `json:"active_mode"`
		SgpayStaticQR  string `json:"sgpay_static_qr"`
		SobpayUsername string `json:"sobpay_username"`
		SharedSecret   string `json:"shared_secret"`
	}

	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, `{"error":"invalid JSON"}`, http.StatusBadRequest)
		return
	}

	if body.ActiveMode != "" {
		_ = h.mgr.Store().SetActiveMode(body.ActiveMode)
	}
	if body.SgpayStaticQR != "" {
		cleanQR := strings.TrimSpace(strings.ReplaceAll(strings.ReplaceAll(body.SgpayStaticQR, "\r", ""), "\n", ""))
		_ = h.mgr.Store().SetSGPayStaticQR(cleanQR)
		h.mgr.SGPay().SetStaticQR(cleanQR)
	}
	if body.SobpayUsername != "" {
		_ = h.mgr.Store().SetSobpayUsername(body.SobpayUsername)
		h.mgr.SoBPay().SetUsername(body.SobpayUsername)
	}
	if body.SharedSecret != "" {
		h.mgr.Dispatcher().SetSharedSecret(body.SharedSecret)
	}

	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"success": true,
		"message": "Configuration updated successfully",
		"status":  h.mgr.GetSystemStatus(),
	})
}
