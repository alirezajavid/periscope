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
- feat/watermark-audio merged.
- feat/text-fingerprint: invisible per-reader marks (U+200B/U+2060, 32-bit code, one per paragraph/list item/quote) added to premium bodies at response time; `TextFingerprint::identify()`; admin leak checks for text and audio (`audiowmark get`) with history in `leak_checks`.
- feat/text-fingerprint merged.
- feat/playback-progress: `PUT /api/posts/{id}/progress` (position + device; doubles as lock heartbeat), `GET /api/continue`, admin listening stats (started, completed ≥ 90 %, completion %, average time).
- feat/playback-progress merged.
- feat/questions-settings: subscriber questions (+ admin star/answer with text and/or episode), key-value settings (links, about/terms/install pages sanitized, maintenance switch, support items with server-made QR SVG for crypto addresses, no extra package).
- feat/questions-settings merged.
- feat/users-and-notifications: admin user list/detail (watermark payload, subscriptions, payments, sessions, logins), block/unblock/logout-all, admin-created accounts (set-password link by email, resend), profile update, new-episode email (`periscope:notify-new-episodes`, every 5 min) with signed one-click unsubscribe.
- feat/users-and-notifications merged.
- feat/seeders: `php artisan db:seed` (local only) + `docs/api.md`, dev switch `WATERMARK_ENABLED=false` (refused in production). Merged, together with feat/password-rules (server enforces the 3 rules the signup screen shows).

## In progress
- feat/app-offline-cache: custom Service Worker (InjectManifest) storing the whole personalized file ~30 s after play starts (Cache Storage, key without the signed query, Range via `workbox-range-requests`), offline rules (7 days since last online check, subscription not ended), stored episodes listed on Home when the API is unreachable, cache cleared on logout / when the subscription ends, `navigator.storage.persist()`.

## Done (app)
- feat/app-content-player (merged): main layout + tab bar + mini player, home, episode, search, full player (preparing state, speed, sleep timer, ±15 s, Media Session, progress + one-device lock), ask, account, plans, payment, payment history, support, change password, maintenance page.

## Done (app)
- feat/app-foundation-auth (merged): app styles/components/layouts + all auth screens (login, register, verify email, forgot/reset password, phone OTP, Google), small API additions (`PUT /api/email`, question limit 500, per-route throttle prefixes).

## Remaining
API is complete (see `docs/api.md`). Left: app screens (home, episode, search, ask, account, plans, payment, player + offline cache SW, static pages, states), then the admin panel UI.

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

- Text mark = first 8 hex digits (32 bits) of the user's 128-bit `watermark_payload`, placed right after an ASCII space; stored text stays clean; free posts are not marked. Stripping the two characters from a marked body gives back the stored body byte for byte (tested), and ZWNJ counts are unchanged.
- **Manual test still needed:** copy a marked paragraph from the app into Telegram and back, and check the invisible characters survive (reference.md §5). Cannot be automated here.

- Admin-created accounts (Telegram subscribers): no permanent password is ever set; the person gets a set-password link (valid 60 min like every reset link). The admin can resend it from the user page (`POST /api/admin/users/{id}/send-password-link`). This answers the open security question in reference.md §6.
- New-episode emails go to active users with a verified email who did not opt out; only posts published within the last day are announced (so importing old episodes does not mail everyone).

- Local run needs `WATERMARK_ENABLED=false` for the API process (no `audiowmark` here): `WATERMARK_ENABLED=false php artisan serve --host=localhost --port=8001`; app: `API_URL=http://localhost:8001 npx quasar dev -m pwa -p 9000`. Demo logins (after `php artisan db:seed`): `subscriber@periscope.test`, `free@periscope.test`, `expired@periscope.test`, `admin@periscope.test` — password `password`; the admin's authenticator secret is `JBSWY3DPEHPK3PXP` (local seed only).
- OTP codes are written to `api/storage/logs/laravel.log` (`SMS_DRIVER=log`); emails too (`MAIL_MAILER=log`).
- Visual checks: Playwright (`playwright-core` in `/tmp/pw`, outside the repo) driving the installed Chrome at 390 px, compared with `docs/design/`.
- UI differences from the prototype (deliberate): header back arrow sits at the reading start (right) next to the title; "edit email" opens an inline form (needs `PUT /api/email`); the prototype's "simulation: click the email link" boxes are not built; the password rule is "upper- and lower-case Latin letters" (what the API enforces) instead of "an upper-case letter".
- Quasar here has no `build.rtl`; RTL is `direction: rtl` in CSS (see `docs/frontend.md`). The IRANSans font file is not in the repo: Tahoma is the fallback until it is added.

- Package note: `workbox-range-requests` was added to `app/src-pwa/package.json` (it was already installed as a Workbox dependency). reference.md §5 names Workbox + RangeRequestsPlugin explicitly; the SW uses its `createPartialResponse` directly because the cache lookup ignores the signed query string.
- The app remembers the last signed-in user in localStorage (`periscope.user`) only to keep stored episodes reachable offline within the 7-day grace period; it is removed on logout.
- To test the SW the app must be built (`API_URL=http://localhost:8001 npx quasar build -m pwa`) and served as static files on port 9000 (the API only allows the dev origins); `python3 -m http.server 9000 -d dist/pwa` is enough (hash routing).

## Problems
- Found by browser testing: `throttle:N,M` without a prefix shares one counter between every route (progress/heartbeat calls ate the payment and login quota). Every throttled route now has its own prefix (test added).
- CI `api` job failed once because tests depended on `SANCTUM_STATEFUL_DOMAINS` in `.env`; fixed in `TestCase`.
