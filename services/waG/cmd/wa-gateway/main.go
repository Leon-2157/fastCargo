package main

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"log"
	"net"
	"net/http"
	"os"
	"os/signal"
	"strconv"
	"strings"
	"syscall"
	"time"

	"wa-gateway/internal/ai"
	"wa-gateway/internal/api"
	"wa-gateway/internal/config"
	"wa-gateway/internal/forwardqueue"
	"wa-gateway/internal/queue"
	"wa-gateway/internal/whatsapp"
)

const AppVersion = "1.1.0"

func main() {
	portFlag := flag.Int("port", 0, "Nomor port server HTTP (contoh: 3000)")
	versionFlag := flag.Bool("version", false, "Tampilkan versi aplikasi wa-gateway")
	flag.Parse()
	if *versionFlag {
		fmt.Printf("wa-gateway version %s\n", AppVersion)
		return
	}

	cfg := config.Get().Snapshot()
	if err := cfg.ValidateForProduction(); err != nil {
		log.Fatalf("konfigurasi production tidak valid: %v", err)
	}
	port := cfg.Port
	if *portFlag > 0 {
		port = *portFlag
	}
	if port < 1 || port > 65535 {
		log.Fatal("port harus berada pada rentang 1 sampai 65535")
	}
	bindAddress := strings.TrimSpace(os.Getenv("HTTP_BIND_ADDRESS"))
	if bindAddress == "" {
		bindAddress = "127.0.0.1"
	}
	if net.ParseIP(bindAddress) == nil && bindAddress != "localhost" {
		log.Fatal("HTTP_BIND_ADDRESS harus berupa alamat IP atau localhost")
	}
	addr := net.JoinHostPort(bindAddress, strconv.Itoa(port))

	_ = ai.GetKnowledgeBase(cfg.AiKnowledgeBasePath)
	log.Print("menginisialisasi penyimpanan WhatsApp dan antrean SQLite")
	if err := whatsapp.GetManager().Init(); err != nil {
		log.Fatalf("inisialisasi WhatsApp gagal: %v", err)
	}
	if err := queue.Init(cfg.SessionDbPath); err != nil {
		_ = whatsapp.GetManager().Shutdown()
		log.Fatalf("inisialisasi antrean gagal: %v", err)
	}
	if err := forwardqueue.Init(cfg.SessionDbPath); err != nil {
		ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer cancel()
		_ = queue.Get().Shutdown(ctx)
		_ = whatsapp.GetManager().Shutdown()
		log.Fatalf("inisialisasi antrean webhook gagal: %v", err)
	}
	supervisorCtx, stopSupervisor := context.WithCancel(context.Background())
	supervisorDone := make(chan struct{})
	go func() { defer close(supervisorDone); superviseWhatsApp(supervisorCtx) }()

	srv := &http.Server{
		Addr:              addr,
		Handler:           api.SetupRouter(),
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       20 * time.Second,
		WriteTimeout:      30 * time.Second,
		IdleTimeout:       60 * time.Second,
		MaxHeaderBytes:    1 << 20,
	}
	serverErr := make(chan error, 1)
	go func() { serverErr <- srv.ListenAndServe() }()
	log.Printf("wa-gateway %s berjalan pada %s", AppVersion, addr)

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, syscall.SIGINT, syscall.SIGTERM)
	select {
	case sig := <-stop:
		log.Printf("menerima sinyal %s; memulai shutdown", sig)
	case err := <-serverErr:
		if !errors.Is(err, http.ErrServerClosed) {
			log.Printf("server HTTP berhenti: %v", err)
		}
	}

	ctx, cancel := context.WithTimeout(context.Background(), 20*time.Second)
	defer cancel()
	stopSupervisor()
	select {
	case <-supervisorDone:
	case <-ctx.Done():
		log.Print("WhatsApp supervisor belum berhenti sebelum batas shutdown")
	}
	if err := srv.Shutdown(ctx); err != nil {
		log.Printf("shutdown HTTP belum tuntas: %v", err)
	}
	if err := queue.Get().Shutdown(ctx); err != nil {
		log.Printf("shutdown antrean belum tuntas: %v", err)
	}
	if err := forwardqueue.Get().Shutdown(ctx); err != nil {
		log.Printf("shutdown antrean webhook belum tuntas: %v", err)
	}
	if err := whatsapp.GetManager().Shutdown(); err != nil {
		log.Printf("shutdown WhatsApp belum tuntas: %v", err)
	}
	log.Print("wa-gateway berhenti")
}

func superviseWhatsApp(ctx context.Context) {
	ticker := time.NewTicker(15 * time.Second)
	defer ticker.Stop()
	for {
		if !whatsapp.GetManager().GetStatus().Paired {
			if err := whatsapp.GetManager().StartConnect(); err != nil {
				log.Printf("WhatsApp belum siap; percobaan ulang dilakukan otomatis")
			}
		}
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
		}
	}
}
