#!/usr/bin/env bash
# Build locally and push to the Iran host with rsync. Run by the developer only.
# Usage: ./deploy.sh [api|app|admin|all] [--dry-run]   (default target: all)
# --dry-run builds locally and lists what rsync would change; the server is not modified.
# Config: copy deploy.env.example to deploy.env and fill it in (never commit deploy.env).
set -euo pipefail

cd "$(dirname "$0")"
root="$(pwd)"
target=all
dry_run=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) dry_run=1 ;;
    api|app|admin|all) target="$arg" ;;
    *) echo "✖ unknown argument: $arg" >&2; exit 1 ;;
  esac
done

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
(( dry_run )) && rsync_base+=(--dry-run --itemize-changes)

# Run on the server; skipped (and printed) in a dry run.
remote_run() {
  if (( dry_run )); then echo "  [dry-run] would run on server: $1"; else $ssh_cmd "$remote" "$1"; fi
}

# State for the api trap below: the site must come back up after a failure only while the database is untouched.
maintenance_on=0
migrate_started=0
api_finished=0

on_exit() {
  local code=$?
  (( code == 0 )) && return
  if (( maintenance_on && ! api_finished )); then
    if (( migrate_started )); then
      echo >&2
      echo "✖ FAILED AFTER MIGRATIONS STARTED. The site is left in maintenance mode on purpose." >&2
      echo "  The database may be partly migrated: check it by hand ('artisan migrate:status') before" >&2
      echo "  running '$REMOTE_PHP artisan up' in $API_PATH. (If migrate had already finished and a later" >&2
      echo "  step failed, the schema is fine and only 'artisan up' after fixing that step is needed.)" >&2
    else
      echo "✖ failed before migrations — bringing the site back up" >&2
      $ssh_cmd "$remote" "cd '$API_PATH' && $REMOTE_PHP artisan up" || echo "  could not run artisan up; do it by hand" >&2
    fi
  fi
}
trap on_exit EXIT

deploy_api() {
  step "api: preparing production build in build/api"
  rm -rf build/api && mkdir -p build
  git archive HEAD api | tar -x -C build
  (cd build/api && composer install --no-dev --optimize-autoloader --no-interaction --no-progress)

  step "api: maintenance mode on"
  # The very first deploy has no artisan on the server yet, so a failure here is expected and ignored.
  remote_run "cd '$API_PATH' && [ -f artisan ] && $REMOTE_PHP artisan down --retry=60 || true"
  (( dry_run )) || maintenance_on=1

  step "api: rsync"
  # .env, storage/ and bootstrap/cache/ live only on the server — excluded paths are never deleted.
  "${rsync_base[@]}" \
    --exclude=/.env --exclude=/storage/ --exclude=/bootstrap/cache/ --exclude=/public/storage --exclude=/tests/ \
    build/api/ "$remote:$API_PATH/"

  step "api: checks on the server"
  remote_run "cd '$API_PATH' && [ -f .env ] || { echo 'missing $API_PATH/.env — create it on the server first' >&2; exit 1; }"
  remote_run "cd '$API_PATH' && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache"

  step "api: migrate, cache, restart workers"
  migrate_started=1
  remote_run "cd '$API_PATH' && $REMOTE_PHP artisan migrate --force"
  remote_run "cd '$API_PATH' && $REMOTE_PHP artisan storage:link --force && $REMOTE_PHP artisan optimize && $REMOTE_PHP artisan queue:restart"
  remote_run "cd '$API_PATH' && $REMOTE_PHP artisan up"
  api_finished=1
}

deploy_frontend() { # $1 = app|admin, $2 = quasar mode, $3 = remote path
  local name="$1" mode="$2" path="$3"
  step "$name: build ($mode)"
  # An empty API_URL makes the app call /api on its own domain (same origin); it overrides any local value.
  (cd "$root/$name" && npm ci --no-audit --no-fund && API_URL= npx quasar build -m "$mode")
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

echo; echo "✔ $( (( dry_run )) && echo "dry run finished for" || echo deployed ) $target ($(git rev-parse --short HEAD))"
