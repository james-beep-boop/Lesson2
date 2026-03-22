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
#  Assumptions:
#    - The DreamHost domain document root points to ~/Lesson2/public
#    - ~/Lesson2 is a full Laravel app clone, not an overlay repo
#    - Frontend assets are built before deploy and included in the release
#      (DreamHost shared hosting does not provide Node.js for production builds)
#    - .env already exists on the server and is not tracked in Git
# ============================================================================

set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/james-beep-boop/Lesson2.git}"
BRANCH="${BRANCH:-main}"
APP_DIR="${APP_DIR:-$HOME/Lesson2}"
SITE_URL="${SITE_URL:-https://www.sheql.com}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

echo "Updating sheql.com from Lesson2..."

require_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "ERROR: Required command not found: $1"
        exit 1
    fi
}

require_cmd git
require_cmd "$PHP_BIN"

old_head=""

if [ ! -d "$APP_DIR/.git" ]; then
    echo "  First-time setup: cloning repository..."
    rm -rf "$APP_DIR.tmp"
    git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$APP_DIR.tmp"
    mv "$APP_DIR.tmp" "$APP_DIR"
else
    echo "  Fetching latest from GitHub..."
    cd "$APP_DIR"

    if [ -n "$(git status --porcelain)" ]; then
        echo "ERROR: $APP_DIR has local changes."
        echo "Commit or discard them before running the deploy script."
        exit 1
    fi

    current_remote="$(git remote get-url origin 2>/dev/null || true)"
    if [ "$current_remote" != "$REPO_URL" ]; then
        echo "ERROR: origin remote does not match the expected Lesson2 repository."
        echo "  Found:    $current_remote"
        echo "  Expected: $REPO_URL"
        exit 1
    fi

    old_head="$(git rev-parse HEAD)"
    git fetch --depth 1 origin "$BRANCH"
    git checkout "$BRANCH"
    git pull --ff-only origin "$BRANCH"
fi

cd "$APP_DIR"
new_head="$(git rev-parse HEAD)"

if [ -z "$old_head" ]; then
    old_head="$new_head"
fi

if [ ! -f .env ]; then
    echo "ERROR: Missing $APP_DIR/.env"
    echo "Create the production .env on DreamHost before deploying."
    exit 1
fi

if [ ! -f artisan ]; then
    echo "ERROR: $APP_DIR does not contain a Laravel app (missing artisan)."
    echo "The current Lesson2 repository appears to be documentation only,"
    echo "so there is nothing deployable to DreamHost yet."
    exit 1
fi

if [ -f package.json ] || find . -maxdepth 1 -type f \( -name 'vite.config.*' -o -name 'next.config.*' -o -name 'astro.config.*' \) | grep -q .; then
    if [ ! -f public/build/manifest.json ]; then
        echo "ERROR: Compiled frontend assets are missing (public/build/manifest.json)."
        echo "DreamHost cannot run npm builds in production."
        echo "Build assets locally or in CI and deploy them with the release."
        exit 1
    fi
fi

need_composer=0
if [ ! -d vendor ]; then
    need_composer=1
elif [ -f composer.json ] && [ -f composer.lock ]; then
    if ! git diff --quiet "$old_head" "$new_head" -- composer.json composer.lock; then
        need_composer=1
    fi
fi

if [ "$need_composer" -eq 1 ]; then
    require_cmd "$COMPOSER_BIN"
    echo "  Installing Composer dependencies..."
    "$COMPOSER_BIN" install --no-dev --optimize-autoloader
else
    echo "  Composer dependencies unchanged; skipping install."
fi

if [ -d storage/app ]; then
    echo "  Writing version info..."
    git rev-parse --short HEAD > storage/app/version.txt
fi

echo "  Running migrations..."
"$PHP_BIN" artisan migrate --force

if [ -d storage/app/public ] && [ ! -L public/storage ]; then
    echo "  Creating storage symlink..."
    "$PHP_BIN" artisan storage:link || true
fi

echo "  Rebuilding caches..."
"$PHP_BIN" artisan optimize:clear --quiet
"$PHP_BIN" artisan cache:forget app_version --quiet 2>/dev/null || true
"$PHP_BIN" artisan config:cache --quiet
"$PHP_BIN" artisan route:cache --quiet
"$PHP_BIN" artisan view:cache --quiet

echo ""
echo "Deployed commit: $(git rev-parse --short HEAD)"
echo "Verify: $SITE_URL"
echo ""
