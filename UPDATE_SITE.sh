#!/bin/bash
# ============================================================================
#  sheql.com — DreamHost Update Script for Lesson2
# ============================================================================
#
#  Run this on DreamHost after you push changes to GitHub:
#
#    ssh david_sheql@sheql.com
#    bash ~/Lesson2/UPDATE_SITE.sh
#
#  First-time setup:
#    1. Create ~/Lesson2/public (mkdir -p ~/Lesson2/public) so DreamHost
#       does not serve a 404 while the clone is running.
#    2. Create ~/Lesson2/.env with production credentials before running.
#    3. The DreamHost domain document root must point to ~/Lesson2/public.
#    4. PHP 8.4 (min 8.3) must be selected for this domain in the panel.
#    5. MySQL hostname on DreamHost is typically mysql.sheql.com, not localhost.
#
#  Assumptions:
#    - The DreamHost domain document root points to ~/Lesson2/public
#    - ~/Lesson2 is a full Laravel app clone, not an overlay repo
#    - Frontend assets are built locally or in CI and uploaded — never committed
#      to the repository. DreamHost has no Node.js for server-side builds.
#    - .env already exists on the server and is not tracked in Git
# ============================================================================

set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/james-beep-boop/Lesson2.git}"
BRANCH="${BRANCH:-main}"
APP_DIR="${APP_DIR:-$HOME/Lesson2}"
SITE_URL="${SITE_URL:-https://www.sheql.com}"

# --- PHP binary detection ---------------------------------------------------
# DreamHost shared hosting may not have 'php' in PATH pointing to the right
# version. Try common DreamHost paths before falling back to the PATH default.
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

# --- Composer binary detection ----------------------------------------------
if [ -z "${COMPOSER_BIN:-}" ]; then
    for candidate in composer composer2 "php $HOME/composer.phar"; do
        if command -v "$candidate" >/dev/null 2>&1; then
            COMPOSER_BIN="$candidate"
            break
        fi
    done
fi
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

# --- Helpers ----------------------------------------------------------------

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

# Ensure maintenance mode is lifted even if the script exits unexpectedly
trap 'maintenance_up' EXIT

# ============================================================================

require_cmd git
require_cmd "$PHP_BIN"

php_min_version "8.3"

echo ""
echo "==> Deploying Lesson2 to sheql.com"
echo "    PHP:    $("$PHP_BIN" -r 'echo PHP_VERSION;')"
echo "    Branch: $BRANCH"
echo ""

old_head=""

if [ ! -d "$APP_DIR/.git" ]; then
    echo "  [1/8] First-time setup: cloning repository..."
    rm -rf "$APP_DIR.tmp"
    git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$APP_DIR.tmp"
    mv "$APP_DIR.tmp" "$APP_DIR"
else
    echo "  [1/8] Fetching latest from GitHub..."
    cd "$APP_DIR"

    if [ -n "$(git status --porcelain | grep -v '^??')" ]; then
        echo "ERROR: $APP_DIR has local changes."
        echo "Commit or discard them before running the deploy script."
        exit 1
    fi

    current_remote="$(git remote get-url origin 2>/dev/null || true)"
    if [ "$current_remote" != "$REPO_URL" ]; then
        echo "ERROR: origin remote does not match expected repository."
        echo "  Found:    $current_remote"
        echo "  Expected: $REPO_URL"
        exit 1
    fi

    old_head="$(git rev-parse HEAD)"
    git fetch --depth 1 origin "$BRANCH"
    git checkout "$BRANCH"
    git reset --hard origin/"$BRANCH"
fi

cd "$APP_DIR"
new_head="$(git rev-parse HEAD)"

if [ -z "$old_head" ]; then
    old_head="$new_head"
fi

# --- Pre-flight checks ------------------------------------------------------

if [ ! -f .env ]; then
    echo "ERROR: Missing $APP_DIR/.env"
    echo "Create the production .env on DreamHost before deploying."
    echo ""
    echo "Minimum required .env keys for DreamHost:"
    echo "  APP_ENV=production"
    echo "  APP_KEY=           (generate with: php artisan key:generate --show)"
    echo "  DB_HOST=mysql.sheql.com"
    echo "  DB_DATABASE="
    echo "  DB_USERNAME="
    echo "  DB_PASSWORD="
    echo "  CACHE_STORE=file"
    echo "  SESSION_DRIVER=file"
    echo "  QUEUE_CONNECTION=sync"
    echo "  MAIL_MAILER=smtp"
    exit 1
fi

if [ ! -f artisan ]; then
    echo "ERROR: $APP_DIR does not contain a Laravel app (missing artisan)."
    echo "The current Lesson2 repository appears to be documentation only,"
    echo "so there is nothing deployable to DreamHost yet."
    exit 1
fi

if [ -f package.json ] || find . -maxdepth 1 -type f \
    \( -name 'vite.config.*' -o -name 'next.config.*' -o -name 'astro.config.*' \) \
    | grep -q .; then
    if [ ! -f public/build/manifest.json ]; then
        echo "ERROR: Compiled frontend assets are missing (public/build/manifest.json)."
        echo "DreamHost cannot run npm builds in production."
        echo "Build assets locally or in CI and include them in the release."
        exit 1
    fi
fi

# --- Composer ---------------------------------------------------------------

echo "  [2/8] Checking Composer dependencies..."

need_composer=0
if [ ! -d vendor ]; then
    need_composer=1
elif [ -f composer.json ] && [ -f composer.lock ]; then
    if ! git diff --quiet "$old_head" "$new_head" -- composer.json composer.lock 2>/dev/null; then
        need_composer=1
    fi
fi

if [ "$need_composer" -eq 1 ]; then
    require_cmd "$COMPOSER_BIN"
    echo "  [2/8] Installing Composer dependencies..."
    "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
else
    echo "  [2/8] Composer dependencies unchanged — skipping install."
fi

# --- Maintenance mode + migrations ------------------------------------------
# Put the site into maintenance mode before running migrations to prevent
# users from hitting a half-migrated database.

echo "  [3/8] Enabling maintenance mode..."
maintenance_down

echo "  [4/8] Running database migrations..."
"$PHP_BIN" artisan migrate --force

# --- Storage symlink --------------------------------------------------------

echo "  [5/8] Checking storage symlink..."
if [ -d storage/app/public ] && [ ! -L public/storage ]; then
    echo "         Creating storage symlink..."
    "$PHP_BIN" artisan storage:link
fi

# --- Cache ------------------------------------------------------------------

echo "  [6/8] Rebuilding caches..."
"$PHP_BIN" artisan optimize:clear --quiet
"$PHP_BIN" artisan config:cache --quiet
"$PHP_BIN" artisan route:cache --quiet
# Omitted intentionally:
#   view:cache  — pre-compiles all Filament vendor templates; 30-60 s on shared
#                 hosting. Laravel compiles views on first use automatically.
#   event:cache — no custom event listeners in this app; empty result, wasted bootstrap.
#   icons:cache — scans every SVG on disk; slow on shared hosting. blade-icons
#                 builds the manifest automatically on the first web request
#                 (triggered by the OPcache-reset HTTP call below).
# Reset Spatie permission cache so the first authenticated request
# after deploy does not hit a cold-cache permission load.
"$PHP_BIN" artisan permission:cache-reset --quiet

# --- Version info -----------------------------------------------------------

echo "  [7/8] Writing version info..."
if [ -d storage/app ]; then
    git rev-parse --short HEAD > storage/app/version.txt
fi

# --- Lift maintenance mode --------------------------------------------------

echo "  [8/8] Lifting maintenance mode..."
maintenance_up

# trap will also call maintenance_up on exit, which is harmless if already up.

# --- OPcache reset ----------------------------------------------------------
# PHP CLI and PHP-FPM (web) have separate OPcache pools. 'php artisan' clears
# the CLI cache only. To invalidate the web OPcache we create a temp PHP file,
# request it over HTTP so it runs inside the FPM process, then delete it.
# This ensures updated PHP files (providers, services, etc.) are re-read on
# the very next request rather than after the OPcache TTL expires.

echo "  [+] Resetting PHP OPcache via web request..."
_RESET_TOKEN=$(openssl rand -hex 16 2>/dev/null || echo "$$$(date +%s)")
_RESET_FILE="$APP_DIR/public/opcache-reset-${_RESET_TOKEN}.php"
echo '<?php if (function_exists("opcache_reset")) { opcache_reset(); } echo "ok";' > "$_RESET_FILE"
curl -sf --max-time 10 "${SITE_URL}/opcache-reset-${_RESET_TOKEN}.php" > /dev/null 2>&1 \
    && echo "       OPcache reset successful." \
    || echo "       OPcache reset skipped (curl unavailable or timed out — harmless)."
rm -f "$_RESET_FILE"

# --- Done -------------------------------------------------------------------

echo ""
echo "=================================================="
echo "  Deployed: $(git rev-parse --short HEAD)"
echo "  Verify:   $SITE_URL"
echo "=================================================="
echo ""
