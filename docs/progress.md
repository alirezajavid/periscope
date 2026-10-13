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

## In progress
- feat/admin-auth (merging)

## Remaining
Per the overnight plan: auth (OTP, Google), admin auth (TOTP), posts, payments/subscriptions, watermark, text fingerprint, remaining APIs, `docs/api.md`, app UI, admin UI.

## Decisions made without confirmation
- Port 8001 for the local API because 8000 is occupied by another project.
- `.env` / `.env.testing` were rewritten from `.env.example` with the MySQL credentials given for the night (previous `.env` kept as git-ignored `.env.backup`).
- Test base class refuses any database not ending in `_test`.

- Admin CSRF: admin and app share the parent cookie domain, so the admin app gets its CSRF token from `GET /api/admin/csrf-token` (JSON) and sends `X-CSRF-TOKEN`, instead of the shared `XSRF-TOKEN` cookie. `@shared/api/client.js` will get an option for this when the admin UI is built.
- A first-time phone number signs up through `/api/otp/login` with a `name`; the response is 201 in that case.
- Unverified-email users can log in (the app shows a banner); gating premium content on verified email is for the posts module to decide.

## Problems
- CI `api` job failed once because tests depended on `SANCTUM_STATEFUL_DOMAINS` in `.env`; fixed in `TestCase`.
