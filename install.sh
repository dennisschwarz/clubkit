#!/usr/bin/env bash
# =============================================================================
#  ClubKit — Installation Script
#  Run as the file owner (the user who owns the project directory).
#  Does NOT require root / sudo.
# =============================================================================
set -e

BOLD=$(tput bold 2>/dev/null || true)
GREEN=$(tput setaf 2 2>/dev/null || true)
YELLOW=$(tput setaf 3 2>/dev/null || true)
RED=$(tput setaf 1 2>/dev/null || true)
RESET=$(tput sgr0 2>/dev/null || true)

ok()   { echo "${GREEN}${BOLD}✔${RESET} $1"; }
info() { echo "${YELLOW}▸${RESET} $1"; }
fail() { echo "${RED}${BOLD}✘ $1${RESET}"; exit 1; }

echo ""
echo "${BOLD}ClubKit Installer${RESET}"
echo "─────────────────────────────────────────────────────────"
echo ""

# ── 1. PHP version check ──────────────────────────────────────────────────────

info "Checking PHP version..."
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null) \
  || fail "PHP not found. Please install PHP 8.2 or higher."

php -r 'if(version_compare(PHP_VERSION, "8.2.0", "<")) exit(1);' \
  || fail "PHP $PHP_VERSION found — ClubKit requires PHP 8.2+."

ok "PHP $PHP_VERSION"

# ── 2. Composer ───────────────────────────────────────────────────────────────

info "Installing Composer dependencies..."
if ! command -v composer &>/dev/null; then
  fail "Composer not found. Install it from https://getcomposer.org"
fi
composer install --no-interaction --prefer-dist --optimize-autoloader
ok "Composer dependencies installed"

# ── 3. Environment file ───────────────────────────────────────────────────────

if [ ! -f ".env" ]; then
  info "Creating .env from .env.example..."
  cp .env.example .env
  ok ".env created — please edit it now before continuing"
  echo ""
  echo "  ${YELLOW}Required settings in .env:${RESET}"
  echo "  APP_URL=https://your-domain.com"
  echo "  DB_DATABASE=clubkit"
  echo "  DB_USERNAME=your_db_user"
  echo "  DB_PASSWORD=your_db_password"
  echo ""
  echo "  After editing .env, run this script again."
  exit 0
else
  ok ".env exists"
fi

# ── 4. App key ────────────────────────────────────────────────────────────────

APP_KEY=$(grep -E '^APP_KEY=' .env | cut -d '=' -f2)
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
  info "Generating application key..."
  php artisan key:generate --no-interaction
  ok "Application key generated"
else
  ok "Application key present"
fi

# ── 5. Storage permissions ────────────────────────────────────────────────────
#
#  On most shared hosting, PHP-FPM runs as the same user as the file owner.
#  chmod 775 is sufficient in that case. If PHP-FPM runs as a different user
#  (e.g. www-data), see INSTALL.md for the ACL approach.
# ─────────────────────────────────────────────────────────────────────────────

info "Setting storage permissions..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || chmod -R 777 storage bootstrap/cache
ok "Permissions set on storage/ and bootstrap/cache/"

# ── 6. Storage symlink ────────────────────────────────────────────────────────

if [ ! -L "public/storage" ]; then
  info "Creating storage symlink..."
  php artisan storage:link --no-interaction
  ok "Storage symlink created"
else
  ok "Storage symlink exists"
fi

# ── 7. Database ───────────────────────────────────────────────────────────────

info "Running database migrations..."
php artisan migrate --no-interaction --force
ok "Migrations complete"

# ── 8. Cache ─────────────────────────────────────────────────────────────────

info "Optimising application..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
ok "Caches cleared"

# ── 9. Admin user ─────────────────────────────────────────────────────────────

echo ""
echo "─────────────────────────────────────────────────────────"
echo "${GREEN}${BOLD}ClubKit installed successfully.${RESET}"
echo ""
echo "  ${YELLOW}Next step:${RESET} Create an admin account by opening"
echo "  your ClubKit URL in the browser and following the setup wizard."
echo ""
echo "  Or via artisan:"
echo "  ${BOLD}php artisan ck:create-admin${RESET}"
echo ""
