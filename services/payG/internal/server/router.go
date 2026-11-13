package server

import (
	"net/http"

	"github.com/go-chi/chi/v5"
	"github.com/go-chi/chi/v5/middleware"

	"fastcargo/payg/internal/manager"
)

func NewRouter(mgr *manager.Manager) http.Handler {
	r := chi.NewRouter()

	r.Use(middleware.RequestID)
	r.Use(middleware.RealIP)
	r.Use(middleware.Logger)
	r.Use(middleware.Recoverer)

	// Basic CORS for admin browser / AJAX calls
	r.Use(func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.Header().Set("Access-Control-Allow-Origin", "*")
			w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
			w.Header().Set("Access-Control-Allow-Headers", "Accept, Authorization, Content-Type, X-CSRF-Token, X-Internal-Secret")
			if r.Method == "OPTIONS" {
				w.WriteHeader(http.StatusOK)
				return
			}
			next.ServeHTTP(w, r)
		})
	})

	statusH := NewStatusHandler(mgr)
	checkoutH := NewCheckoutHandler(mgr)
	sgpayH := NewSGPayHandler(mgr)
	sobpayH := NewSoBPayHandler(mgr)

	// Health check
	r.Get("/health", statusH.Health)

	// API Routes
	r.Route("/api", func(api chi.Router) {
		api.Get("/status", statusH.GetStatus)
		api.Get("/transactions", statusH.GetTransactions)
		api.Post("/config", statusH.UpdateConfig)

		// Checkout
		api.Post("/checkout/create", checkoutH.CreateCheckout)
		api.Get("/checkout/{ref}/status", checkoutH.GetTransactionStatus)

		// Simulator
		api.Post("/simulator/settle", checkoutH.SimulateSettle)

		// SGpay endpoints
		api.Post("/sgpay/otp/request", sgpayH.RequestOTP)
		api.Post("/sgpay/otp/verify", sgpayH.VerifyOTP)
		api.Post("/sgpay/disconnect", sgpayH.Disconnect)
		api.Post("/sgpay/static-qr", sgpayH.ConfigureStaticQR)

		// SoBpay endpoints
		api.Post("/sobpay/config", sobpayH.Configure)
		api.Get("/sobpay/fees", sobpayH.GetFees)
	})

	return r
}
