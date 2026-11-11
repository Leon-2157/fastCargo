## FastCargo — Development Makefile
## Jalankan: make <target>

.PHONY: up down restart migrate fresh seed logs shell artisan

# Jalankan semua container
up:
	podman-compose up -d

# Hentikan semua container
down:
	podman-compose down

# Restart semua container
restart:
	podman-compose restart

# Jalankan migration
migrate:
	podman-compose exec php php artisan migrate

# Reset database + migrate + seed
fresh:
	podman-compose exec php php artisan migrate:fresh --seed

# Seed saja
seed:
	podman-compose exec php php artisan db:seed

# Lihat log semua container
logs:
	podman-compose logs -f

# Lihat log container tertentu: make logs-php
logs-%:
	podman-compose logs -f $*

# Masuk ke shell container PHP
shell:
	podman-compose exec php sh

# Jalankan artisan command: make artisan CMD="route:list"
artisan:
	podman-compose exec php php artisan $(CMD)

# Build image ulang
build:
	podman-compose build --no-cache

# Cache config untuk production
cache:
	podman-compose exec php php artisan config:cache
	podman-compose exec php php artisan route:cache
	podman-compose exec php php artisan view:cache

# Clear semua cache
clear:
	podman-compose exec php php artisan config:clear
	podman-compose exec php php artisan route:clear
	podman-compose exec php php artisan view:clear
	podman-compose exec php php artisan cache:clear

# Install npm dan build assets
npm-build:
	npm install && npm run build

# Dev mode Vite
npm-dev:
	npm run dev

