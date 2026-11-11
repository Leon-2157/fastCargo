package api

import (
	"crypto/subtle"
	"net/http"
	"strings"
	"time"

	"github.com/gin-gonic/gin"

	"wa-gateway/internal/config"
	"wa-gateway/internal/dashboard"
)

func SetupRouter() *gin.Engine {
	if config.Get().Snapshot().AppEnv == "production" {
		gin.SetMode(gin.ReleaseMode)
	}

	r := gin.New()
	r.Use(gin.Recovery())
	// Only trust forwarded addresses from explicitly configured proxy networks.
	proxyCIDRs := make([]string, 0)
	for _, cidr := range strings.Split(config.Get().Snapshot().TrustedProxyCIDRs, ",") {
		if cidr = strings.TrimSpace(cidr); cidr != "" {
			proxyCIDRs = append(proxyCIDRs, cidr)
		}
	}
	if err := r.SetTrustedProxies(proxyCIDRs); err != nil {
		_ = r.SetTrustedProxies(nil)
	}
	r.Use(limitRequestBody(1 << 20))
	r.Use(func(c *gin.Context) {
		c.Header("X-Content-Type-Options", "nosniff")
		c.Header("Referrer-Policy", "strict-origin-when-cross-origin")
		c.Header("X-Frame-Options", "DENY")
		if !strings.HasPrefix(c.Request.URL.Path, "/assets/") {
			c.Header("Cache-Control", "no-store")
		}
		c.Next()
	})

	// Same-origin is the default. Cross-origin access must be explicitly listed.
	r.Use(func(c *gin.Context) {
		origin := c.GetHeader("Origin")
		allowed := false
		for _, candidate := range strings.Split(config.Get().Snapshot().CorsAllowedOrigins, ",") {
			if origin != "" && strings.TrimSpace(candidate) == origin {
				allowed = true
				break
			}
		}

		if c.Request.Method == "OPTIONS" {
			if !allowed {
				c.AbortWithStatus(http.StatusForbidden)
				return
			}
			c.Header("Access-Control-Allow-Origin", origin)
			c.Header("Vary", "Origin")
			c.Header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
			c.Header("Access-Control-Allow-Headers", "Origin, Content-Type, Accept, Authorization, X-Gateway-Secret")
			c.AbortWithStatus(http.StatusNoContent)
			return
		}
		if allowed {
			c.Header("Access-Control-Allow-Origin", origin)
			c.Header("Vary", "Origin")
		}
		c.Next()
	})

	// 1. Web Dashboard & Visual Pages
	r.GET("/", dashboard.ServeIndex)
	r.GET("/assets/:name", dashboard.ServeAsset)
	r.GET("/admin", func(c *gin.Context) { c.Redirect(http.StatusMovedPermanently, "/") })
	r.GET("/dashboard", func(c *gin.Context) { c.Redirect(http.StatusMovedPermanently, "/") })

	// 2. Health & Status
	r.GET("/health", handleHealth)
	r.GET("/status", handleStatus)
	r.GET("/ready", handleReadiness)
	r.GET("/webhooks/whatsapp", handleWhatsAppWebhookVerify)
	r.POST("/webhooks/whatsapp", rateLimit("whatsapp-webhook", 600, time.Minute), handleWhatsAppWebhook)

	// 3. Fast-Lane OTP Endpoints
	r.POST("/api/otp/send", rateLimit("otp-send", 10, time.Hour), handleOtpSend)
	r.POST("/api/otp/verify", rateLimit("otp-verify", 60, 15*time.Minute), handleOtpVerify)

	// 4. Outgoing Message, Internal Status/QR & Socket Controls (Protected by Gateway Secret)
	authGroup := r.Group("/")
	authGroup.Use(secretAuthMiddleware())
	authGroup.Use(rateLimit("gateway-actions", 60, time.Minute))
	{
		authGroup.GET("/internal/status", handleInternalStatus)
		authGroup.GET("/qr", handleInternalQr)
		authGroup.POST("/send", handleSendMessage)
		authGroup.GET("/send/:id", handleSendJobStatus)
		authGroup.POST("/logout", handleLogout)
		authGroup.POST("/restart", handleRestart)
	}

	// 5. Dashboard Management API
	dashGroup := r.Group("/api/dashboard")
	{
		dashGroup.POST("/login", rateLimit("dashboard-login", 10, 15*time.Minute), handleDashboardLogin)

		// Protected dashboard routes
		dashProtected := dashGroup.Group("/")
		dashProtected.Use(dashboardAuthMiddleware())
		dashProtected.Use(rateLimit("dashboard", 300, time.Minute))
		{
			dashProtected.GET("/status", handleDashboardStatus)
			dashProtected.GET("/config", handleDashboardGetConfig)
			dashProtected.POST("/config", handleDashboardSaveConfig)
			dashProtected.GET("/knowledge-base", handleDashboardGetKB)
			dashProtected.POST("/knowledge-base", handleDashboardSaveKB)
			dashProtected.POST("/pairing-code", handleDashboardPairingCode)
			dashProtected.GET("/logs", handleDashboardLogs)
			dashProtected.POST("/change-password", rateLimit("dashboard-password-change", 10, time.Hour), handleDashboardChangePassword)
			dashProtected.POST("/logout", handleDashboardLogout)
			dashProtected.POST("/send", rateLimit("dashboard-send", 60, time.Minute), handleSendMessage)
			dashProtected.GET("/messages/:id", handleSendJobStatus)
			dashProtected.POST("/socket/restart", handleRestart)
			dashProtected.POST("/socket/logout", handleLogout)
			dashProtected.POST("/ai-simulate", rateLimit("dashboard-ai", 30, time.Minute), handleDashboardAiSimulate)
		}
	}

	return r
}

// Secret Auth Middleware for internal REST API calls (from Laravel, Flutter, etc.)
func secretAuthMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		cfg := config.Get().Snapshot()
		if cfg.GatewaySecret == "" {
			c.AbortWithStatusJSON(http.StatusServiceUnavailable, gin.H{
				"status": false,
				"error":  "gateway secret belum dikonfigurasi",
			})
			return
		}

		secret := c.GetHeader("X-Gateway-Secret")
		if secret == "" {
			secret = c.GetHeader("x-gateway-secret")
		}
		if len(secret) != len(cfg.GatewaySecret) || subtle.ConstantTimeCompare([]byte(secret), []byte(cfg.GatewaySecret)) != 1 {
			c.AbortWithStatusJSON(http.StatusUnauthorized, gin.H{
				"status": false,
				"error":  "Unauthorized: Invalid gateway secret",
			})
			return
		}
		c.Next()
	}
}

// Dashboard Token Middleware for Router Web Panel
func dashboardAuthMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		cookie, err := c.Request.Cookie("gw_session")
		token := ""
		if err == nil {
			token = cookie.Value
		}

		if !dashboard.GetAuth().ValidateSession(token) {
			c.AbortWithStatusJSON(http.StatusUnauthorized, gin.H{
				"success": false,
				"error":   "Sesi login telah kedaluwarsa atau tidak valid",
			})
			return
		}
		c.Set("dashboard_token", token)
		c.Next()
	}
}
