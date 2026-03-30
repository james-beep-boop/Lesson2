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
#    PHP_BIN=/usr/local/php84/bin/php
#    ALLOW_DIRTY=1
# ============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

REMOTE_HOST="${REMOTE_HOST:-david_sheql@sheql.com}"
REMOTE_APP_DIR="${REMOTE_APP_DIR:-~/Lesson2}"
REMOTE_SCRIPT="${REMOTE_SCRIPT:-UPDATE_SITE.sh}"
ALLOW_DIRTY="${ALLOW_DIRTY:-0}"

require_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "ERROR: Required command not found: $1"
        exit 1
    fi
}

require_cmd rsync
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

RELEASE_COMMIT="$(git rev-parse --short HEAD 2>/dev/null || echo local)"

echo ""
echo "==> Deploying Lesson2 to DreamHost via rsync"
echo "    Host:    $REMOTE_HOST"
echo "    App dir: $REMOTE_APP_DIR"
echo "    Release: $RELEASE_COMMIT"
echo ""

echo "  [1/2] Uploading prepared app files..."
rsync -az --delete \
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
    --exclude 'AGENTS.md' \
    --exclude 'CLAUDE.md' \
    --exclude 'Lesson2.md' \
    --exclude 'PROGRESS.md' \
    --exclude 'deployment.md' \
    --exclude 'dreamhost.md' \
    --exclude 'troubleshooting.md' \
    ./ "$REMOTE_HOST:$REMOTE_APP_DIR/"

echo "  [2/2] Finalizing deploy on DreamHost..."
ssh "$REMOTE_HOST" "cd $REMOTE_APP_DIR && RELEASE_COMMIT=$RELEASE_COMMIT bash ./$REMOTE_SCRIPT"

echo ""
echo "Deployment complete."
echo ""
