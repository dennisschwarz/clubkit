#!/bin/bash
# ClubKit Deploy Script
# Server: /var/www/clients/client_1/web_21/web/clubkit/
# Aufruf: bash deploy.sh

set -e

PROJECT="/var/www/clients/client_1/web_21/web/clubkit"
PHP="/usr/bin/php8.5"
COMPOSER="/usr/local/bin/composer"
WEB_USER="web21"
WEB_GROUP="client1"

echo "==> ClubKit Deployment gestartet"
cd "$PROJECT"

echo "==> Maintenance Mode an"
sudo -u "$WEB_USER" "$PHP" artisan down --retry=30

echo "==> Git Pull"
git pull origin main

echo "==> Composer Install"
sudo -u "$WEB_USER" "$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction

echo "==> NPM Build"
sudo -u "$WEB_USER" npm ci
sudo -u "$WEB_USER" npm run build

echo "==> Migrationen"
sudo -u "$WEB_USER" "$PHP" artisan migrate --force

echo "==> Cache optimieren"
sudo -u "$WEB_USER" "$PHP" artisan optimize

echo "==> Berechtigungen setzen"
chown -R "$WEB_USER":"$WEB_GROUP" storage bootstrap/cache public/build
chmod -R 775 storage bootstrap/cache

echo "==> Queue Worker neu starten"
sudo -u "$WEB_USER" "$PHP" artisan queue:restart 2>/dev/null || true

echo "==> Maintenance Mode aus"
sudo -u "$WEB_USER" "$PHP" artisan up

echo ""
echo "✅ Deployment abgeschlossen."
