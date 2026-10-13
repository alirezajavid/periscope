# Periscope

Subscription PWA for the Periscope podcast (پریسکوپ). Subscribers listen to and read premium episodes; admins run everything from a separate panel. Built by a single volunteer developer — **the rule for every decision is "the least work that solves the real problem."** Do not add features, layers, packages or infrastructure that were not asked for.

Full product/technical decisions (Persian): @docs/reference.md — treat decisions marked «بسته‌شده» as closed unless the user reopens them.

## Repository layout

| Path | What | Details |
|---|---|---|
| `api/` | Laravel API (MySQL, Redis, queue via Supervisor) | `api/CLAUDE.md` |
| `app/` | Quasar user app, PWA mode → `app.periscopepod.com` | `app/CLAUDE.md` |
| `admin/` | Quasar admin panel, SPA (no PWA) → `admin.periscopepod.com` | `admin/CLAUDE.md` |
| `shared/` | Plain JS shared by both apps, imported as `@shared/...` | `docs/frontend.md` |
| `docs/` | Reference doc, frontend conventions, setup guide | |
| `deploy.sh` | Local build + rsync to the Iran host | |

First-time setup of the three projects: `docs/setup.md`.

## Versions (must match the production server)

PHP 8.3 · MySQL 8.0 · Redis 7 · Node 22 LTS · Laravel and Quasar 2 (Vite) at their latest stable major at setup time.
After setup, record the exact Laravel major here: `Laravel __`. Do not upgrade any of these without the user asking.

## Non-negotiable domain rules

- **All logic and data live on the Iran host.** The foreign host is only a one-way mirror for audio/images.
- **Premium content never leaves the server for an unauthorised user.** Non-subscribers get title, cover and `excerpt` only — never `body`, never an audio URL.
- **Audio is watermarked per user** (audiowmark + ffmpeg, 128-bit `watermark_payload`). Never serve the original premium audio file to a user.
- **Text is watermarked per user at API response time** with zero-width characters (U+200B, U+2060). Stored text stays clean. **Never insert, remove or alter ZWNJ (U+200C)** — it is part of Persian spelling.
- Post HTML is sanitized server-side with HTMLPurifier; whitelist `p, h2, h3, strong, em, ul, ol, li, blockquote, a, br`. Raw embed code is never rendered with `v-html`.
- Users cannot delete their account; admins block. Posts use SoftDeletes.
- Admin: separate cookie/domain, mandatory TOTP (Fortify), 30-minute idle timeout, no remember-me.
- User sessions: Sanctum SPA cookies, 60-day sliding session in Redis, invalidated when browser family or OS family changes. Concurrent playback on one device only.

## Language

- Code, identifiers, comments, commit messages, PR text: **English**.
- User-facing text: **Persian**, never hard-coded in components — frontend strings live in `src/i18n/fa.json` (vue-i18n); API validation/error messages come from Laravel `lang/fa`.
- Talk to the user (Alireza) in Persian. In documents, the client is «آقای منتظری».

## Workflow

- `main` is always deployable. Every task on its own branch: `feat/…`, `fix/…`, `chore/…`, `docs/…`.
- Conventional Commits with scope: `feat(api): approve manual payment`, `fix(app): resume position after reload`. Scopes: `api`, `app`, `admin`, `shared`, `docs`, `ci`, `deploy`.
- Merge via PR only; the user reviews before merging. Keep PRs to one module/feature.
- Build module by module in this order: auth → posts → player & watermark → admin panel. The user tests and approves each module before the next starts.
- Before saying a task is done, run the checks for every part you touched (commands below) and make sure they pass.

## Checks

Formatting runs automatically after every edit (`.claude/hooks/format.sh`). Before finishing a task run:

```bash
# api/
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && php artisan test
# app/ or admin/
npm run lint && npm run build
```

Tests (Pest) are **required** for: auth & sessions, subscriber-only access to content, payment approval & subscription activation, watermarking, admin authorization. Other code needs no tests unless asked.

## Never

- Edit an existing migration that has been merged to `main` — add a new one.
- Run `migrate:fresh`, `db:wipe` or anything destructive against a database you did not create for tests.
- Read or print `.env` files or `deploy.env`; commit secrets.
- Run `deploy.sh` — deployment is done by the user.
- Add a package without saying why the framework can't do it.
