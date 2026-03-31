#!/bin/bash
# ============================================================================
#  sheql.com — DreamHost Finalize Script for Lesson2
# ============================================================================
#
#  This script runs on DreamHost after the prepared app has already been
#  uploaded from a local machine or CI. DreamHost shared hosting is treated as
#  a runtime host only: no Composer install, no npm build, no git reset.
#
#  Typical usage on DreamHost:
#
#    cd ~/Lesson2
#    RELEASE_COMMIT=<git-sha> bash ~/Lesson2/UPDATE_SITE.sh
#
#  Assumptions:
#    - The full Laravel app has already been copied into ~/Lesson2
#    - vendor/ is already present
#    - public/build/manifest.json is already present
#    - .env already exists on the server and is not overwritten by deploys
#    - The DreamHost domain document root points to ~/Lesson2/public
# ============================================================================

set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/Lesson2}"
SITE_URL="${SITE_URL:-https://www.sheql.com}"
RELEASE_COMMIT="${RELEASE_COMMIT:-unknown}"

# --- PHP binary detection ---------------------------------------------------
if [ -z "${PHP_BIN:-}" ]; then
    for candidate in \
        /usr/local/php84/bin/php \
        /usr/local/php83/bin/php \
        php84 \
        php8.4 \
        php8.3 \
        php; do
        if command -v "$candidate" >/dev/null 2>&1; then
            PHP_BIN="$candidate"
            break
        fi
    done
fi
PHP_BIN="${PHP_BIN:-php}"

require_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "ERROR: Required command not found: $1"
        exit 1
    fi
}

php_min_version() {
    local required="$1"
    local actual
    actual=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "0.0")
    if [ "$(printf '%s\n%s' "$required" "$actual" | sort -V | head -1)" != "$required" ]; then
        echo "ERROR: PHP $required or higher required. Found: $actual (using $PHP_BIN)"
        echo "Set PHP_BIN to the correct binary, e.g.:"
        echo "  PHP_BIN=/usr/local/php84/bin/php bash ~/Lesson2/UPDATE_SITE.sh"
        exit 1
    fi
}

maintenance_down() {
    "$PHP_BIN" artisan down --retry=10 --secret="deploy-$(date +%s)" --quiet 2>/dev/null || true
}

maintenance_up() {
    "$PHP_BIN" artisan up --quiet 2>/dev/null || true
}

_OPCACHE_RESET_FILE=""
cleanup() {
    maintenance_up
    [ -n "$_OPCACHE_RESET_FILE" ] && rm -f "$_OPCACHE_RESET_FILE"
}
trap 'cleanup' EXIT

require_cmd "$PHP_BIN"
php_min_version "8.3"

cd "$APP_DIR"

echo ""
echo "==> Finalizing Lesson2 deploy on sheql.com"
echo "    PHP:     $("$PHP_BIN" -r 'echo PHP_VERSION;')"
echo "    Release: $RELEASE_COMMIT"
echo ""

if [ ! -f .env ]; then
    echo "ERROR: Missing $APP_DIR/.env"
    echo "Create the production .env on DreamHost before running this script."
    exit 1
fi

if [ ! -f artisan ]; then
    echo "ERROR: $APP_DIR does not contain a Laravel app (missing artisan)."
    exit 1
fi

if [ ! -f vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php is missing."
    echo "Upload Composer dependencies from your local machine before running this script."
    exit 1
fi

if [ ! -f public/build/manifest.json ]; then
    echo "ERROR: Compiled frontend assets are missing (public/build/manifest.json)."
    echo "Build assets locally or in CI, upload them, then rerun this script."
    exit 1
fi

echo "  [1/7] Enabling maintenance mode..."
maintenance_down

echo "  [2/7] Running database migrations..."
"$PHP_BIN" artisan migrate --force

echo "  [3/7] Checking storage symlink..."
if [ -d storage/app/public ] && [ ! -L public/storage ]; then
    echo "         Creating storage symlink..."
    "$PHP_BIN" artisan storage:link
fi

echo "  [4/7] Publishing Filament assets..."
"$PHP_BIN" artisan filament:assets --quiet

echo "  [5/7] Rebuilding caches..."
"$PHP_BIN" artisan optimize:clear --quiet
"$PHP_BIN" artisan config:cache --quiet
"$PHP_BIN" artisan route:cache --quiet
"$PHP_BIN" artisan view:cache --quiet
"$PHP_BIN" artisan icons:cache --quiet
"$PHP_BIN" artisan permission:cache-reset --quiet

echo "  [6/7] Writing version info..."
mkdir -p storage/app
printf '%s\n' "$RELEASE_COMMIT" > storage/app/version.txt

echo "  [7/7] Lifting maintenance mode..."
maintenance_up

# PHP CLI and PHP-FPM (web) have separate OPcache pools. 'php artisan' clears
# the CLI cache only. To invalidate the web OPcache we create a temp PHP file,
# request it over HTTP so it runs inside the FPM process, then delete it.

echo "  [+] Resetting PHP OPcache via web request..."
_RESET_TOKEN=$(openssl rand -hex 16 2>/dev/null || echo "$$$(date +%s)")
_OPCACHE_RESET_FILE="$APP_DIR/public/opcache-reset-${_RESET_TOKEN}.php"
echo '<?php if (function_exists("opcache_reset")) { opcache_reset(); } echo "ok";' > "$_OPCACHE_RESET_FILE"
curl -sf --max-time 10 "${SITE_URL}/opcache-reset-${_RESET_TOKEN}.php" > /dev/null 2>&1 \
    && echo "       OPcache reset successful." \
    || echo "       OPcache reset skipped (curl unavailable or timed out — harmless)."
rm -f "$_OPCACHE_RESET_FILE"
_OPCACHE_RESET_FILE=""

echo ""
echo "=================================================="
echo "  Finalized release: $RELEASE_COMMIT"
echo "  Verify:            $SITE_URL"
echo "=================================================="
echo ""
