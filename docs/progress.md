# Progress

Read this file and `CLAUDE.md` first in every new session.

## Environment notes
- Local ports: API `8001` (port 8000 is taken by another local project, Apache), app `9000`, admin `9001`.
- Run: `cd api && php artisan serve --host=localhost --port=8001`, `php artisan queue:work redis`; `cd app && npx quasar dev -m pwa -p 9000`; `cd admin && npx quasar dev -p 9001`.
- Prerequisites found missing: `audiowmark` (watermark tests use `Process::fake()`).
- UI font: B Yekan+ (`BYekan`, 400/700) added to both apps on 1405-07-22, replacing the IRANSans plan.
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

## Done (app)
- feat/app-foundation-auth, feat/app-content-player, feat/app-offline-cache (all merged): styles/components/layouts, all auth screens, main layout with tab bar and mini player, home, episode, search, full player (preparing state, speed, sleep timer, ±15 s, Media Session, progress + one-device lock), ask, account, plans, payment, payment history, support, change password, maintenance page, and the silent offline audio cache (custom Service Worker, 30 s after play, 7-day grace, cleared on logout / subscription end, `navigator.storage.persist()`).

## Done (admin)
- feat/admin-foundation, feat/admin-users-payments, feat/admin-community-settings (all merged): login with TOTP setup/challenge, posts list and editor (cover, audio, watermark status), users and user detail, payment queue, question inbox, listening stats, leak check, settings (links, support items, fixed pages, plans, payment methods, maintenance).

## In progress
- Nothing.

## Remaining (needs the owner or a real environment)
- Real SMS provider (driver is `log`; OTP codes are in `api/storage/logs/laravel.log`).
- Real plan prices (seed prices are placeholders) and the payment texts from the owner.
- Install `audiowmark` and run the real-episode test from reference.md §11 (time and audio quality); the audio leak check also needs it.
- Copy a marked paragraph into Telegram and back to confirm the invisible marks survive.
- Production: mail service, foreign mirror disk/URL, backups (`spatie/laravel-backup` is planned before the first real subscriber), HTTPS cookie domains, Supervisor for the queue and cron for the scheduler. Deployment is done by the owner (`deploy.sh`).
- Test the PWA install and offline cache on a real phone (Android and iOS).

## How to run locally
```bash
# api (port 8001; WATERMARK_ENABLED=false because audiowmark is not installed here)
cd api && php artisan migrate && php artisan db:seed
WATERMARK_ENABLED=false php artisan serve --host=localhost --port=8001
php artisan queue:work redis --queue=watermark-priority,watermark,default   # second terminal
php artisan schedule:work                                                   # third terminal
# user app (PWA)
cd app && API_URL=http://localhost:8001 npx quasar dev -m pwa -p 9000
# admin panel
cd admin && API_URL=http://localhost:8001 npx quasar dev -p 9001
```
- Demo users (password `password`): `subscriber@periscope.test`, `free@periscope.test`, `expired@periscope.test`, `blocked@periscope.test`, and the admin `admin@periscope.test`.
- Admin two-factor: the seeded admin's authenticator secret is `JBSWY3DPEHPK3PXP` (local seed only); add it to any authenticator app as a manual key. A new admin made with `php artisan periscope:make-admin <email> --name … --password …` sets up the authenticator on first login (QR + recovery codes shown once).
- To test the offline cache the app must be built and served statically: `API_URL=http://localhost:8001 npx quasar build -m pwa` then `python3 -m http.server 9000 -d dist/pwa`.

## Differences from the prototype (deliberate)
- Header back arrow sits at the reading start (right) next to the title (RTL).
- "Edit email" opens an inline form; the prototype's "simulation: click the email link" boxes are not built.
- Password rule is "upper- and lower-case Latin letters" (what the API enforces).
- Admin panel is plain Quasar by design (no prototype styling), sidebar on the right.

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
- Quasar here has no `build.rtl`; RTL is `direction: rtl` in CSS (see `docs/frontend.md`).

- Package note: `workbox-range-requests` was added to `app/src-pwa/package.json` (it was already installed as a Workbox dependency). reference.md §5 names Workbox + RangeRequestsPlugin explicitly; the SW uses its `createPartialResponse` directly because the cache lookup ignores the signed query string.
- The app remembers the last signed-in user in localStorage (`periscope.user`) only to keep stored episodes reachable offline within the 7-day grace period; it is removed on logout.
- To test the SW the app must be built (`API_URL=http://localhost:8001 npx quasar build -m pwa`) and served as static files on port 9000 (the API only allows the dev origins); `python3 -m http.server 9000 -d dist/pwa` is enough (hash routing).

## Problems
- Found by browser testing: `throttle:N,M` without a prefix shares one counter between every route (progress/heartbeat calls ate the payment and login quota). Every throttled route now has its own prefix (test added).
- CI `api` job failed once because tests depended on `SANCTUM_STATEFUL_DOMAINS` in `.env`; fixed in `TestCase`.

- Admin UI: RTL by CSS like the app; CSRF token comes from `/api/admin/csrf-token`; logging in regenerates the session, so the shared client refetches the token and repeats a request once after a 419. The setup QR is shown as an `<img>` with a data URL, never `v-html`.
- Problem: one commit (6962207) reached `main` directly because the ship helper was run while on `main`; the helper now refuses `main`. Nothing in it was wrong in CI terms, but it skipped the PR step.
- Admin sidebar: this Quasar version mirrors layout offsets for RTL but its stylesheet keeps `.q-drawer--left` on the physical left; `admin/src/css/app.scss` pins it to the right so the sidebar and the content offset agree.
- Admin: users (list, create, detail with block/unblock, logout everywhere, resend set-password link, manual subscription) and the payment queue (receipt shown from a blob fetched with the admin session, approve with optional start date, reject with reason; both behind a confirm dialog that states the consequence) are done and checked in the browser at 1440 and 390 px.
- Admin: question inbox (star, text answer and/or "answered in episode", confirm before answering), listening stats, leak check (text and audio, with history) and settings (links and support items, fixed pages with the limited editor, plans and prices per currency, payment methods, maintenance mode with a confirm dialog) are done and checked in the browser at 1440 and 390 px. Audio leak check was not run for real because `audiowmark` is not installed here.

وضعیت: تمام شد
