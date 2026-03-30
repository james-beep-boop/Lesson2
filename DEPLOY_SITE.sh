#!/bin/bash
# ============================================================================
#  Local deploy helper for Lesson2 -> DreamHost via rsync
# ============================================================================
#
#  Run this from your local machine after you have installed dependencies,
#  built assets, and verified the app:
#
#    bash ./DEPLOY_SITE.sh
#
#  Optional overrides:
#    REMOTE_HOST=david_sheql@sheql.com
#    REMOTE_APP_DIR=~/Lesson2
#    REMOTE_SCRIPT=UPDATE_SITE.sh
#    PHP_BIN=/opt/homebrew/bin/php
#    ALLOW_DIRTY=1
# ============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

REMOTE_HOST="${REMOTE_HOST:-david_sheql@sheql.com}"
REMOTE_APP_DIR="${REMOTE_APP_DIR:-~/Lesson2}"
REMOTE_SCRIPT="${REMOTE_SCRIPT:-UPDATE_SITE.sh}"
ALLOW_DIRTY="${ALLOW_DIRTY:-0}"
SSH_OPTS="${SSH_OPTS:--o ServerAliveInterval=30 -o ServerAliveCountMax=6}"

if [ -z "${PHP_BIN:-}" ]; then
    for candidate in /opt/homebrew/bin/php /usr/local/bin/php php84 php8.4 php8.3 php; do
        if command -v "$candidate" >/dev/null 2>&1; then
            PHP_BIN="$candidate"
            break
        fi
    done
fi
PHP_BIN="${PHP_BIN:-php}"

if [ -z "${RSYNC_BIN:-}" ]; then
    for candidate in /opt/homebrew/bin/rsync /usr/local/bin/rsync rsync; do
        if command -v "$candidate" >/dev/null 2>&1; then
            RSYNC_BIN="$candidate"
            break
        fi
    done
fi
RSYNC_BIN="${RSYNC_BIN:-rsync}"

require_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "ERROR: Required command not found: $1"
        exit 1
    fi
}

require_cmd "$PHP_BIN"
require_cmd "$RSYNC_BIN"
require_cmd ssh

if [ "$ALLOW_DIRTY" != "1" ] && [ -n "$(git status --porcelain)" ]; then
    echo "ERROR: Working tree has uncommitted changes."
    echo "Commit or stash them first, or rerun with ALLOW_DIRTY=1 if intentional."
    exit 1
fi

if [ ! -f vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php is missing. Run composer install locally first."
    exit 1
fi

if [ ! -f public/build/manifest.json ]; then
    echo "ERROR: public/build/manifest.json is missing. Run npm run build locally first."
    exit 1
fi

echo "  [preflight] Verifying local Composer autoload..."
"$PHP_BIN" -r "require 'vendor/autoload.php'; echo 'autoload ok', PHP_EOL;" >/dev/null

echo "  [preflight] Verifying local Laravel bootstrap..."
"$PHP_BIN" artisan about >/dev/null

RELEASE_COMMIT="$(git rev-parse --short HEAD 2>/dev/null || echo local)"

echo ""
echo "==> Deploying Lesson2 to DreamHost via rsync"
echo "    Host:    $REMOTE_HOST"
echo "    App dir: $REMOTE_APP_DIR"
echo "    Release: $RELEASE_COMMIT"
echo "    PHP:     $($PHP_BIN -r 'echo PHP_VERSION;')"
echo "    rsync:   $($RSYNC_BIN --version | head -n 1)"
echo "    ssh:     $SSH_OPTS"
echo ""

echo "  [1/4] Uploading app code..."
"$RSYNC_BIN" -az -e "ssh $SSH_OPTS" --delete --delete-delay --force \
    --exclude '.git/' \
    --exclude '.DS_Store' \
    --exclude '.env' \
    --exclude '.claude/' \
    --exclude '.codex/' \
    --exclude '.cursor/' \
    --exclude '.agents/' \
    --exclude '.mcp.json' \
    --exclude 'node_modules/' \
    --exclude 'storage/' \
    --exclude 'tests/' \
    --exclude 'vendor/' \
    --exclude 'public/build/' \
    --exclude 'public/js/filament/' \
    --exclude 'public/css/filament/' \
    --exclude 'public/fonts/filament/' \
    --exclude 'AGENTS.md' \
    --exclude 'CLAUDE.md' \
    --exclude 'Lesson2.md' \
    --exclude 'PROGRESS.md' \
    --exclude 'deployment.md' \
    --exclude 'dreamhost.md' \
    --exclude 'troubleshooting.md' \
    ./ "$REMOTE_HOST:$REMOTE_APP_DIR/"

echo "  [2/4] Uploading Composer dependencies..."
"$RSYNC_BIN" -az -e "ssh $SSH_OPTS" --delete --delete-delay --force \
    vendor/ "$REMOTE_HOST:$REMOTE_APP_DIR/vendor/"

echo "  [3/4] Uploading built frontend assets..."
"$RSYNC_BIN" -az -e "ssh $SSH_OPTS" --delete --delete-delay --force \
    public/build/ "$REMOTE_HOST:$REMOTE_APP_DIR/public/build/"

echo "  [4/4] Finalizing deploy on DreamHost..."
ssh -tt $SSH_OPTS "$REMOTE_HOST" "cd $REMOTE_APP_DIR && RELEASE_COMMIT=$RELEASE_COMMIT bash ./$REMOTE_SCRIPT"

echo ""
echo "Deployment complete."
echo ""
