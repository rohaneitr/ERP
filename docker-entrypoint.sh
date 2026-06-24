#!/bin/bash
set -e

echo "──────────────────────────────────────────────"
echo "  FastPos — Fast Technologies"
echo "  Starting application..."
echo "──────────────────────────────────────────────"

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "[FastPos] Generating APP_KEY..."
    php artisan key:generate --force
fi

# Run Laravel optimizations
echo "[FastPos] Caching config..."
php artisan config:cache

echo "[FastPos] Caching routes..."
php artisan route:cache

# Skip view:cache to avoid crashing on missing custom/module view directories
# Views will be compiled on-demand by Laravel.

echo "[FastPos] Running migrations..."
php artisan migrate --force --no-interaction

echo "[FastPos] Clearing old caches..."
php artisan cache:clear

echo "[FastPos] Storage link..."
php artisan storage:link || true

echo "──────────────────────────────────────────────"
echo "  FastPos ready!"
echo "  SUPERADMIN_MODE: ${SUPERADMIN_MODE:-false}"
echo "  APP_URL: ${APP_URL}"
echo "──────────────────────────────────────────────"

# Start Apache (webdevops image default)
exec /entrypoint supervisord
