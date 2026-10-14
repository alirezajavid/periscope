# Production deploy

`deploy.sh` is run by the developer only, from a clean `main` that equals `origin/main`:

```bash
git checkout main && git pull
./deploy.sh --dry-run     # builds locally, lists what rsync would change, touches nothing on the server
./deploy.sh               # api + app + admin   (or: ./deploy.sh api|app|admin)
```

Config is in `deploy.env` (git-ignored, see `deploy.env.example`). `REMOTE_PHP` is the PHP binary on the server (`php8.3`).

## What it does
- **api:** composer (no-dev) locally → `artisan down` (ignored if it fails: first deploy) → rsync (never sends or deletes `.env`, `storage/`, `bootstrap/cache/`, `public/storage`) → checks `.env` exists on the server and creates the `storage/` skeleton → `migrate --force` → `storage:link --force`, `optimize`, `queue:restart` → `artisan up`.
- If anything fails **before** migrate, the site is brought back up. If it fails **after migrate started**, the site stays in maintenance mode and the script says so: check `artisan migrate:status` by hand, then run `artisan up`.
- **app / admin:** `npm ci` + `quasar build` with an empty `API_URL` (the apps call `/api` on their own domain) → rsync with `--delete`.

## Server prerequisites
- nginx serves `/var/www/periscope/app` and `/var/www/periscope/admin` as static files and sends `/api` and `/sanctum` of both domains to Laravel (`/var/www/periscope/api/public`). Do not cache `/api`.
- `deploy` owns the three directories; `www-data` must be able to write `api/storage` and `api/bootstrap/cache` (e.g. group `www-data`, `chmod -R g+rwX`, setgid on those dirs).
- `api/.env` is created on the server only. Domain-dependent values: `APP_URL`, `PERISCOPE_APP_URL`, `PERISCOPE_ADMIN_URL`, `SANCTUM_STATEFUL_DOMAINS` (both hosts, comma separated), `SESSION_DOMAIN=null`, plus `APP_ENV=production`, `APP_DEBUG=false`, and never `WATERMARK_ENABLED=false`.
- Supervisor runs `php8.3 artisan queue:work redis --queue=watermark-priority,watermark,default`; cron runs `php8.3 artisan schedule:run` every minute.
- No sudo is needed by the script.
