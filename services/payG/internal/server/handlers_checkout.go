package server

import (
	"encoding/json"
	"net/http"

	"github.com/go-chi/chi/v5"

	"fastcargo/payg/internal/core"
	"fastcargo/payg/internal/manager"
)

type CheckoutHandler struct {
	mgr *manager.Manager
}

func NewCheckoutHandler(mgr *manager.Manager) *CheckoutHandler {
	return &CheckoutHandler{mgr: mgr}
}

func (h *CheckoutHandler) CreateCheckout(w http.ResponseWriter, r *http.Request) {
	var req core.CheckoutRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		_ = json.NewEncoder(w).Encode(map[string]any{
			"success": false,
			"error":   "Invalid request payload: " + err.Error(),
		})
		return
	}

	if req.Amount <= 0 {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusBadRequest)
		_ = json.NewEncoder(w).Encode(map[string]any{
			"success": false,
			"error":   "Amount must be greater than zero",
		})
		return
	}

	resp, err := h.mgr.CreateCheckout(r.Context(), req)
	if err != nil {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusInternalServerError)
		_ = json.NewEncoder(w).Encode(map[string]any{
			"success": false,
			"error":   err.Error(),
		})
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	_ = json.NewEncoder(w).Encode(map[string]any{
		"success": true,
		"data":    resp,
	})
}

func (h *CheckoutHandler) GetTransactionStatus(w http.ResponseWriter, r *http.Request) {
	ref := chi.URLParam(r, "ref")
	if ref == "" {
		http.Error(w, `{"error":"missing reference"}`, http.StatusBadRequest)
		return
	}

	tx, ok := h.mgr.Store().GetTransaction(ref)
	if !ok {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusNotFound)
		_ = json.NewEncoder(w).Encode(map[string]any{
			"success": false,
			"error":   "Transaction not found",
		})
		return
	}

	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(map[string]any{
		"success": true,
		"data":    tx,
	})
}

func (h *CheckoutHandler) SimulateSettle(w http.ResponseWriter, r *http.Request) {
	var body struct {
		TransactionRef string `json:"transaction_ref"`
		Scenario       string `json:"scenario"` // "SETTLEMENT", "EXPIRED", "FAILED"
	}

	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, `{"error":"invalid JSON"}`, http.StatusBadRequest)
		return
	}

	if body.TransactionRef == "" {
		http.Error(w, `{"error":"transaction_ref is required"}`, http.StatusBadRequest)
		return
	}

	scenario := body.Scenario
	if scenario == "" {
		scenario = "SETTLEMENT"
	}

	if scenario == "FAILED" || scenario == "EXPIRED" || scenario == "CANCELLED" {
		err := h.mgr.FailTransaction(r.Context(), body.TransactionRef, scenario, map[string]any{
			"simulated": true,
			"scenario":  scenario,
			"source":    "simulator_button",
		})
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
			"success": true,
			"message": "Transaction marked as " + scenario + " and webhook dispatched",
			"ref":     body.TransactionRef,
		})
		return
	}

	err := h.mgr.SettleTransaction(r.Context(), body.TransactionRef, map[string]any{
		"simulated": true,
		"scenario":  body.Scenario,
		"source":    "simulator_button",
	})

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
		"success": true,
		"message": "Transaction settled and webhook dispatched",
		"ref":     body.TransactionRef,
	})
}
