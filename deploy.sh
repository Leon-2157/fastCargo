#!/usr/bin/env bash
set -euo pipefail

cd /opt/fastcargo

echo "==> [1/5] Syncing latest code from origin/main..."
git fetch origin main
git reset --hard origin/main

echo "==> [2/5] Building frontend Vite assets..."
npm install --no-save
npm run build

echo "==> [3/5] Rebuilding and starting Docker Compose stack (with Cloudflare Tunnel)..."
docker compose -f podman-compose.yml --profile tunnel up -d --build

echo "==> [4/5] Running database migrations..."
docker exec fastcargo-php-1 php artisan migrate --force

echo "==> [5/5] Refreshing Laravel caches..."
docker exec fastcargo-php-1 php artisan optimize:clear
docker exec fastcargo-php-1 php artisan optimize

echo "==> Deploy completed successfully!"

