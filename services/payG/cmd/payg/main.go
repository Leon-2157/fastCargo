package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"fastcargo/payg/internal/config"
	"fastcargo/payg/internal/manager"
	"fastcargo/payg/internal/server"
	"fastcargo/payg/internal/store"
)

func main() {
	cfg := config.Load()
	log.Printf("[PayG] Starting %s on %s:%s (Env: %s)", cfg.AppName, cfg.HTTPBindAddress, cfg.Port, cfg.AppEnv)

	st, err := store.NewStore(cfg.StoragePath)
	if err != nil {
		log.Fatalf("[PayG] Failed to initialize state store: %v", err)
	}

	mgr := manager.NewManager(cfg, st)
	mgr.StartBackgroundPoller()

	router := server.NewRouter(mgr)
	addr := fmt.Sprintf("%s:%s", cfg.HTTPBindAddress, cfg.Port)

	srv := &http.Server{
		Addr:         addr,
		Handler:      router,
		ReadTimeout:  30 * time.Second,
		WriteTimeout: 30 * time.Second,
		IdleTimeout:  120 * time.Second,
	}

	go func() {
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("[PayG] HTTP server error: %v", err)
		}
	}()

	log.Printf("[PayG] HTTP daemon listening on http://%s", addr)

	// Graceful shutdown
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, os.Interrupt, syscall.SIGTERM)
	<-quit

	log.Println("[PayG] Shutting down...")
	mgr.Stop()

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := srv.Shutdown(ctx); err != nil {
		log.Printf("[PayG] Server forced to shutdown: %v", err)
	}

	log.Println("[PayG] Server exited cleanly.")
}
