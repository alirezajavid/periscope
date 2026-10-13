# First-time setup

Run once, on a branch `chore/scaffold`. Claude Code can follow this file step by step.
The `api/`, `app/` and `admin/` folders already contain a `CLAUDE.md`, so each project is scaffolded in a temp folder and moved in.

## 0. Local prerequisites (native install, same versions as the server)

PHP 8.3 (+ extensions: mbstring, xml, curl, mysql, redis, zip, intl, bcmath, gd), Composer, MySQL 8.0, Redis 7, Node 22 LTS, `jq` (used by the Claude formatting hook), `ffmpeg`, `audiowmark`.

Create databases `periscope` and `periscope_test`.

## 1. api/

```bash
composer create-project laravel/laravel /tmp/periscope-api
cp -rn /tmp/periscope-api/. api/ && rm -rf /tmp/periscope-api
cd api
php artisan install:api                      # Sanctum + routes/api.php
composer require laravel/fortify mews/purifier
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan --with-all-dependencies
./vendor/bin/pest --init
```

`api/pint.json`:
```json
{ "preset": "laravel" }
```

`api/phpstan.neon`:
```neon
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    paths:
        - app/
    level: 5
```

Then: `.env` → MySQL `periscope`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`; `.env.testing` → `DB_DATABASE=periscope_test`; set `APP_LOCALE=fa`, add `lang/fa`; create `routes/admin.php` registered with prefix `api/admin`; add `config/periscope.php`. Record the exact Laravel major in the root `CLAUDE.md`.

## 2. app/ (PWA) and admin/ (SPA)

`npm init quasar` is interactive — choose: **Quasar v2, Vite, JavaScript, Composition API `<script setup>`, Sass, ESLint + Prettier, Pinia, vue-i18n, axios**.

```bash
cd /tmp && npm init quasar@latest      # project folder: periscope-app
cp -rn /tmp/periscope-app/. app/
cd app && npx quasar mode add pwa
```

Repeat for `admin/` (no PWA mode).

In both `quasar.config.js`:
- `build.alias: { '@shared': path.resolve(__dirname, '../shared') }`
- `framework.lang: 'fa-IR'`, RTL on (`build.rtl: true` / `framework.config` as the Quasar version requires)
- brand colors from `docs/reference.md` §۹ in `src/css/quasar.variables.scss`

Rename the i18n file to `src/i18n/fa.json` and set `fa` as the only/default locale.

## 3. shared/

Create `shared/api/client.js`, `shared/utils/date.js`, `shared/utils/number.js` as described in `docs/frontend.md`.

## 4. Verify, then open the PR

```bash
(cd api && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && php artisan test)
(cd app && npm run lint && npx quasar build -m pwa)
(cd admin && npm run lint && npx quasar build)
```

Commit as `chore: scaffold api, app and admin`, push, open a PR — CI must be green.
