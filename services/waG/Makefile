.PHONY: all build run install clean reset-password docker-build docker-up docker-down

BINARY_NAME=wa-gateway
SRC=./cmd/wa-gateway

all: build

build:
	@mkdir -p bin
	@echo "🔨 Meng-compile biner $(BINARY_NAME)..."
	go build -o bin/$(BINARY_NAME) $(SRC)
	@echo "✅ Biner berhasil dibuat di bin/$(BINARY_NAME)"

run:
	@echo "🚀 Menjalankan $(BINARY_NAME)..."
	go run $(SRC)

install:
	@mkdir -p $(HOME)/.local/bin
	@echo "📦 Meng-install $(BINARY_NAME) ke $(HOME)/.local/bin/..."
	go build -o $(HOME)/.local/bin/$(BINARY_NAME) $(SRC)
	@echo "✅ Perintah 'wa-gateway' sekarang bisa dipanggil dari mana saja di terminal!"

reset-password:
	@go run $(SRC) --reset-password

clean:
	@rm -rf bin/
	@echo "🧹 Bersih."

docker-build:
	docker build -t wa-gateway .

docker-up:
	docker compose up -d

docker-down:
	docker compose down
