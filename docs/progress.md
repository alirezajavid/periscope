# Progress

Read this file and `CLAUDE.md` first in every new session.

## Environment notes
- Local ports: API `8001` (port 8000 is taken by another local project, Apache), app `9000`, admin `9001`.
- Run: `cd api && php artisan serve --host=localhost --port=8001`, `php artisan queue:work redis`; `cd app && npx quasar dev -m pwa -p 9000`; `cd admin && npx quasar dev -p 9001`.
- Prerequisites found missing: `audiowmark` (watermark tests use `Process::fake()`), IRANSans font file (fallback Tahoma).
- Inputs (zip files) are kept outside the repo in `/home/douran/Projects/periscope-inputs`; the prototype PNGs are in `docs/design/`.

## Done
- chore/scaffold, feat/auth (email + password, sessions, device-family check), chore/project-conventions — merged.
- feat/auth-otp-google: phone OTP (driver `log`), Google via Socialite — merged.
- feat/admin-auth: admin login with mandatory TOTP (Fortify actions), own session cookie `periscope_admin_session`, 30 min idle timeout, admin_logs, `php artisan periscope:make-admin {email}`.
- feat/admin-auth merged. (Order changed: payments/subscriptions before posts, because `PostPolicy` needs real subscriptions to test both sides.)
- feat/payments-subscriptions: plans + prices (IRT/USD/USDT), payment methods, manual payments with receipt, approve (admin picks start date) / reject, manual subscriptions, emails.
- feat/payments-subscriptions merged.
- feat/posts: posts/tags, PostPolicy (preview vs full content), HTMLPurifier whitelist (ZWNJ preserved), scheduling by `published_at`, public + admin APIs, cover/audio upload, MirrorFileToForeignHost job, RSS of free episodes + public audio of free episodes.
- feat/posts merged.
- feat/watermark-audio: `GeneratePersonalizedAudio` job (ffmpeg → audiowmark → 64 kbps MP3 via `Process`, argument arrays), `periscope:build-watermarks` (hourly, 3 h before publish, recently active listeners first), `periscope:cleanup-watermarks` (7-day keep), signed relative URLs (2 h), `GET /api/posts/{id}/audio?device=` (ready/preparing), one-device playback lock with heartbeat/release, admin watermark counts + failed list + retry.

## In progress
- feat/watermark-audio (merging)

## Remaining
Per the overnight plan: auth (OTP, Google), admin auth (TOTP), posts, payments/subscriptions, watermark, text fingerprint, remaining APIs, `docs/api.md`, app UI, admin UI.

## Decisions made without confirmation
- Port 8001 for the local API because 8000 is occupied by another project.
- `.env` / `.env.testing` were rewritten from `.env.example` with the MySQL credentials given for the night (previous `.env` kept as git-ignored `.env.backup`).
- Test base class refuses any database not ending in `_test`.

- Admin CSRF: admin and app share the parent cookie domain, so the admin app gets its CSRF token from `GET /api/admin/csrf-token` (JSON) and sends `X-CSRF-TOKEN`, instead of the shared `XSRF-TOKEN` cookie. `@shared/api/client.js` will get an option for this when the admin UI is built.
- A first-time phone number signs up through `/api/otp/login` with a `name`; the response is 201 in that case.
- Unverified-email users can log in (the app shows a banner); gating premium content on verified email is for the posts module to decide.

- One pending payment per user at a time (stops queue spam). Plans and payment methods are deactivated, never deleted (payments reference them).
- Prices in `plan_prices` are placeholders until the owner decides; seeders will use demo prices.

- Body search covers premium text only for readers who may open it (a non-subscriber could otherwise probe premium text through search).
- Free-episode audio is public (`/api/posts/{id}/free-audio`, Range supported) because the RSS feed must point to it; premium audio is never exposed this way.
- `embed_code` is stored as pasted; the API only exposes `embed_url`, extracted for hosts in `config('periscope.embed_hosts')` (aparat.com).
- Foreign mirror disk: `FOREIGN_DISK` (default local folder `storage/app/foreign`); a real remote disk is configured at deploy time.

- Run a worker for all queues: `php artisan queue:work redis --queue=watermark-priority,watermark,default` and the scheduler (`php artisan schedule:work` locally, cron `schedule:run` in production).
- Audio URLs are signed on the path only (`signed:relative`), so the same URL works through the foreign mirror's reverse proxy (`PERISCOPE_FOREIGN_URL`) for users who chose that server.
- `audiowmark` is not installed on this machine: the pipeline is tested with `Process::fake()` only. It must be tried with a real episode before launch (see the command at the end of reference.md §11).

## Problems
- CI `api` job failed once because tests depended on `SANCTUM_STATEFUL_DOMAINS` in `.env`; fixed in `TestCase`.
