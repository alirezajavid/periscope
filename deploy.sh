#!/usr/bin/env bash
# Build locally and push to the Iran host with rsync. Run by the developer only.
# Usage: ./deploy.sh [api|app|admin|all]   (default: all)
# Config: copy deploy.env.example to deploy.env and fill it in (never commit deploy.env).
set -euo pipefail

cd "$(dirname "$0")"
root="$(pwd)"
target="${1:-all}"

die() { echo "✖ $*" >&2; exit 1; }
step() { echo; echo "▶ $*"; }

[[ -f deploy.env ]] || die "deploy.env not found (see deploy.env.example)"
# shellcheck source=/dev/null
source deploy.env
: "${DEPLOY_HOST:?}" "${DEPLOY_USER:?}" "${DEPLOY_PORT:=22}" "${API_PATH:?}" "${APP_PATH:?}" "${ADMIN_PATH:?}" "${REMOTE_PHP:=php}"

[[ "$(git rev-parse --abbrev-ref HEAD)" == main ]] || die "deploy only from main"
git diff --quiet && git diff --cached --quiet || die "uncommitted changes"
git fetch -q origin main
[[ "$(git rev-parse HEAD)" == "$(git rev-parse origin/main)" ]] || die "local main differs from origin/main — pull/push first"

remote="$DEPLOY_USER@$DEPLOY_HOST"
ssh_cmd="ssh -p $DEPLOY_PORT"
rsync_base=(rsync -az --delete -e "$ssh_cmd")

deploy_api() {
  step "api: preparing production build in build/api"
  rm -rf build/api && mkdir -p build
  git archive HEAD api | tar -x -C build
  (cd build/api && composer install --no-dev --optimize-autoloader --no-interaction --no-progress)

  step "api: maintenance mode on"
  $ssh_cmd "$remote" "cd '$API_PATH' && [ -f artisan ] && $REMOTE_PHP artisan down --retry=60 || true"

  step "api: rsync"
  # .env, storage/ and bootstrap/cache/ live only on the server — excluded paths are never deleted.
  "${rsync_base[@]}" \
    --exclude=/.env --exclude=/storage/ --exclude=/bootstrap/cache/ --exclude=/public/storage --exclude=/tests/ \
    build/api/ "$remote:$API_PATH/"

  step "api: migrate, cache, restart workers"
  $ssh_cmd "$remote" "cd '$API_PATH' && \
    $REMOTE_PHP artisan migrate --force && \
    $REMOTE_PHP artisan optimize && \
    $REMOTE_PHP artisan queue:restart && \
    $REMOTE_PHP artisan up"
}

deploy_frontend() { # $1 = app|admin, $2 = quasar mode, $3 = remote path
  local name="$1" mode="$2" path="$3"
  step "$name: build ($mode)"
  (cd "$root/$name" && npm ci --no-audit --no-fund && npx quasar build -m "$mode")
  step "$name: rsync"
  "${rsync_base[@]}" "$root/$name/dist/$mode/" "$remote:$path/"
}

case "$target" in
  api)   deploy_api ;;
  app)   deploy_frontend app pwa "$APP_PATH" ;;
  admin) deploy_frontend admin spa "$ADMIN_PATH" ;;
  all)   deploy_api; deploy_frontend app pwa "$APP_PATH"; deploy_frontend admin spa "$ADMIN_PATH" ;;
  *)     die "unknown target: $target" ;;
esac

echo; echo "✔ deployed $target ($(git rev-parse --short HEAD))"
