#!/usr/bin/env bash
# =============================================================================
#  ClubKit — Update Script
#  Run as the file owner. Does NOT require root / sudo.
# =============================================================================
set -e

BOLD=$(tput bold 2>/dev/null || true)
GREEN=$(tput setaf 2 2>/dev/null || true)
YELLOW=$(tput setaf 3 2>/dev/null || true)
RESET=$(tput sgr0 2>/dev/null || true)

ok()   { echo "${GREEN}${BOLD}✔${RESET} $1"; }
info() { echo "${YELLOW}▸${RESET} $1"; }

echo ""
echo "${BOLD}ClubKit Update${RESET}"
echo "─────────────────────────────────────────────────────────"
echo ""

info "Pulling latest changes..."
git pull --ff-only
ok "Code updated"

info "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader
ok "Dependencies updated"

info "Refreshing storage permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || chmod -R 777 storage bootstrap/cache

info "Running database migrations..."
php artisan migrate --no-interaction --force
ok "Migrations complete"

info "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
ok "Caches cleared"

echo ""
echo "─────────────────────────────────────────────────────────"
echo "${GREEN}${BOLD}ClubKit updated successfully.${RESET}"
echo ""
