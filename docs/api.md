# API reference

Base URL: `http://localhost:8001` in development (`API_URL` in the frontends). All paths below are under `/api`.
Responses are JSON. Resources are wrapped in `{ "data": ... }`; lists are paginated Laravel-style (`data`, `links`, `meta`).
Errors: `{ "message": "...", "errors": { "field": ["..."] } }` (422 for validation). Messages are Persian (`lang/fa`).

## Conventions

**Authentication — subscribers (app).** Sanctum SPA cookies. Before the first write, call `GET /sanctum/csrf-cookie`; the client then sends the `XSRF-TOKEN` cookie as `X-XSRF-TOKEN` (axios does this with `withCredentials` + `withXSRFToken`). Requests must come from an origin listed in `SANCTUM_STATEFUL_DOMAINS`.
Session: 60 days sliding, stored in Redis. A change of browser family or OS family ends the session (401, message `auth.device_changed`). A blocked user gets 403.

**Authentication — admins (admin panel).** Everything under `/api/admin/*` uses a **separate session cookie** (`periscope_admin_session`, 30 min idle, no remember-me). Because the XSRF cookie would be shared with the app, the admin client takes its token from `GET /api/admin/csrf-token` and sends it as `X-CSRF-TOKEN`. Login is two steps (password, then TOTP); nobody is authenticated between them.

**Access column.** `public` = no login · `user` = any signed-in user · `subscriber` = signed in with an active subscription · `admin` = signed-in admin who passed TOTP. "Optional" = works anonymously, but the response depends on who asks.

**Premium content.** A premium post is returned to non-subscribers as a preview: `locked: true`, with title, cover, excerpt and metadata only. `body` and `embed_url` are absent and no audio URL ever appears in a post (audio has its own endpoint).

**Rate limits.** Login-type endpoints are throttled (429). Retry later.

---

## Auth — subscribers

| Method & path | Access | Input | Output |
|---|---|---|---|
| `POST /register` | public | `name`, `email`, `password`, `password_confirmation` (min 8) | 201 user; signed in; verification email sent |
| `POST /login` | public | `email`, `password` | user. 422 wrong credentials, 403 blocked, 429 throttled (5/min) |
| `POST /logout` | user | — | 204 |
| `POST /logout-all` | user | — | 204; ends every session of the user, including this one |
| `GET /user` | user | — | user: `id, name, email, email_verified, phone, phone_verified, notify_new_episode, preferred_mirror` |
| `PUT /profile` | user | any of `name`, `notify_new_episode` (bool), `preferred_mirror` (`ir`\|`intl`) | user |
| `PUT /password` | user | `current_password`, `password`, `password_confirmation` | message; **ends every session** (sign in again) |
| `POST /forgot-password` | public | `email` | same message whether or not the account exists |
| `POST /reset-password` | public | `token`, `email`, `password`, `password_confirmation` | message; ends every session of that user |
| `GET /email/verify/{id}/{hash}` | public (signed link) | — | redirect to the app (`/#/email-verified`) |
| `POST /email/resend` | user | — | message |
| `POST /otp/send` | public | `phone` (09…, +98…, Persian digits ok) | message. 429 within 60 s of the previous code. In development the code is written to `storage/logs/laravel.log` (`SMS_DRIVER=log`) |
| `POST /otp/login` | public | `phone`, `code` (6 digits), `name` (only for a first-time number) | user (201 on sign-up, 200 on sign-in). 422 on `code`, or on `name` when it is needed (the code is not spent then). 5 wrong attempts lock the code (5 min lifetime) |
| `POST /phone` | user | `phone` | message; sends a code to attach a number to the account |
| `POST /phone/verify` | user | `phone`, `code` | user with `phone_verified: true` |
| `GET /auth/google/redirect` | public (browser navigation, not XHR) | — | redirect to Google |
| `GET /auth/google/callback` | public | — | signs in, redirects to `<app>/#/`; on failure `<app>/#/login?error=google` or `error=blocked` |
| `GET /unsubscribe/{user}` | public (signed link from the email) | — | turns new-episode emails off; redirect `<app>/#/unsubscribed` |

## Content

| Method & path | Access | Input | Output |
|---|---|---|---|
| `GET /posts` | optional | `q` (title, excerpt, hashtags; body only where the reader may read it), `tag` (hashtag name), `page` | paginated posts (newest first), `PostResource` below |
| `GET /posts/{id}` | optional | — | post. 404 for drafts, scheduled and deleted posts |
| `GET /tags` | public | — | `data: [{ name, posts_count }]` (only tags with visible posts) |
| `GET /posts/{id}/audio?device=<id>` | optional | `device`: a random id stored by the client | **free:** `{status: "ready", url}` · **subscriber:** `{status: "ready", url, duration}` (signed URL to *their own watermarked copy*, valid ~2 h) or **202** `{status: "preparing"}` (a build was queued; poll again) · 401 anonymous · 403 not a subscriber · **409** `{code: "playing_elsewhere"}` another device is playing |
| `GET /audio/{file}` | signed URL only | — | MP3 stream (Range supported). 403 if the signature is missing/invalid/expired |
| `GET /posts/{id}/free-audio` | public | — | MP3 of a **free** episode (Range supported); 404 for premium |
| `GET /feed/free.xml` | public | — | RSS 2.0 with free episodes only |
| `POST /playback/heartbeat` | user | `device` | 204; call every ~30 s while playing. 409 if another device holds the lock |
| `POST /playback/release` | user | `device` | 204; call on pause/stop |
| `PUT /posts/{id}/progress` | user (subscriber for premium) | `position` (seconds), `device` | `{position, completed}`; also refreshes the playback lock. 409 as above |
| `GET /posts/{id}/progress` | user | — | `{position, completed}` |
| `GET /continue` | user | — | `data: [{ post, position, remaining }]` unfinished episodes, newest first |
| `GET /settings` | public | — | `data`: `social_links`, `page_about`, `page_terms`, `page_install_guide`, `maintenance`, `maintenance_message` (null when never set) |
| `GET /support` | public | — | `data: [{ title, kind: link\|crypto, value, network?, note?, qr_svg }]` (`qr_svg` only for crypto) |

**PostResource**: `id, title, episode_number, excerpt, cover_url, access (free|premium), published_at, sponsor_name, sponsor_url, has_audio, audio_duration, tags[], locked` and, when `locked` is false, `body` (purified HTML; premium bodies carry the reader's invisible mark) and `embed_url` (https URL on an allowed video host, e.g. aparat.com; build the iframe from it, never render raw embed code).

## Subscription and payments

| Method & path | Access | Input | Output |
|---|---|---|---|
| `GET /plans` | public | — | `data: [{ id, title, months, prices: [{currency, amount}] }]` (active plans; currencies `IRT`, `USD`, `USDT`) |
| `GET /payment-methods` | user | — | `data: [{ id, type: card\|paypal\|crypto, title, instructions, currency }]` |
| `POST /payments` | user | multipart: `plan_id`, `payment_method_id`, `tracking_code`, `receipt` (image ≤ 5 MB) | 201 payment (`status: pending`). The amount comes from the plan's price in the method's currency, never from the client. 422 if a payment is already pending or the plan has no price in that currency |
| `GET /payments` | user | — | own payments, newest first |
| `GET /subscription` | user | — | `{ active, ends_at, subscriptions: [{plan, starts_at, ends_at}] }` |
| `GET /questions` | user | — | own questions with answers (`status: new\|starred\|answered`, `answer`, `answered_in_post`) |
| `POST /questions` | subscriber | `body` (5–2000 chars) | 201 question. 403 for non-subscribers |

---

## Admin — login (own session cookie)

| Method & path | Access | Input | Output |
|---|---|---|---|
| `GET /admin/csrf-token` | public | — | `{token}` — send as `X-CSRF-TOKEN` |
| `POST /admin/login` | public | `email`, `password` | `{status: "two_factor_setup_required" \| "two_factor_required"}`. 422 for unknown user / non-admin / wrong password (same answer), 403 blocked, 429 (5/min) |
| `POST /admin/two-factor/setup` | after password step | — | `{secret, qr_svg, qr_url, recovery_codes[8]}` (first login only) |
| `POST /admin/two-factor/confirm` | after password step | `code` (6 digits from the authenticator app) | admin user; **logged in** |
| `POST /admin/two-factor/challenge` | after password step | `code` or `recovery_code` | admin user; logged in. Recovery codes work once |
| `GET /admin/user` | admin | — | the admin |
| `POST /admin/logout` | admin | — | 204 |

After 30 minutes without a request an admin call returns 401 (`auth.admin_idle`).
Create the first admin: `php artisan periscope:make-admin {email}` (asks for name/password when the email is new).

## Admin — content

| Method & path | Input | Output |
|---|---|---|
| `GET /admin/posts` | `q`, `status`, `access`, `trashed=1` | paginated `AdminPostResource` incl. `watermarks: {ready, building, failed}` |
| `GET /admin/posts/{id}` | — | post (also soft-deleted) |
| `POST /admin/posts` | `title`, `episode_number?`, `excerpt?`, `body?`, `access`, `status`, `published_at?`, `sponsor_name?`, `sponsor_url?`, `embed_code?`, `tags[]` | 201. Publishing needs a body, audio or embed; `published_at` defaults to now. A future `published_at` schedules the post |
| `PUT /admin/posts/{id}` | same fields | post |
| `DELETE /admin/posts/{id}` | — | 204 (soft delete) |
| `POST /admin/posts/{id}/restore` | — | post |
| `POST /admin/posts/{id}/cover` | multipart `cover` (jpg/png/webp ≤ 5 MB) | post |
| `POST /admin/posts/{id}/audio` | multipart `audio` (mp3/m4a/wav ≤ 300 MB) | post; stored on the private disk, mirrored to the foreign host by a job |
| `GET /admin/posts/{id}/watermarks` | `status?` | per-user copies with `error` for failed ones |
| `POST /admin/posts/{id}/watermarks/retry` | — | `{queued}` failed copies re-queued |
| `GET /admin/tags` | — | `data: [names]` for autocomplete |

`AdminPostResource` adds `status, embed_code, audio_size, audio_mirrored_at, created_by, deleted_at` to the public fields and always includes `body`.

## Admin — people and money

| Method & path | Input | Output |
|---|---|---|
| `GET /admin/users` | `q`, `status`, `role`, `subscribed=0\|1` | paginated users |
| `GET /admin/users/{id}` | — | user detail incl. `watermark_payload`, subscriptions, payments, active sessions, last 20 logins |
| `POST /admin/users` | `name`, `email` and/or `phone`, `plan_id?`, `starts_at?`, `note?` | 201. No password is set; the person gets an email with a set-password link |
| `POST /admin/users/{id}/send-password-link` | — | message |
| `POST /admin/users/{id}/block` · `unblock` | — | user (blocking ends all sessions; you cannot block yourself) |
| `POST /admin/users/{id}/logout-all` | — | 204 |
| `POST /admin/users/{id}/subscriptions` | `plan_id`, `starts_at?`, `note?` | 201 subscription (manual grant) |
| `GET /admin/payments` | `status?`, `page` | queue, oldest first |
| `GET /admin/payments/{id}` | — | payment with user, plan, method |
| `GET /admin/payments/{id}/receipt` | — | the receipt image |
| `POST /admin/payments/{id}/approve` | `starts_at?` | payment; creates the subscription (default start = end of the current one, else now) and emails the user. 409 if already reviewed |
| `POST /admin/payments/{id}/reject` | `reason` | payment; emails the user with the reason. 409 if already reviewed |
| `GET/POST /admin/plans`, `PUT /admin/plans/{id}` | `title`, `months`, `active`, `sort_order`, `prices[{currency, amount}]` | plans are deactivated, not deleted |
| `GET/POST /admin/payment-methods`, `PUT /admin/payment-methods/{id}` | `type`, `title`, `instructions`, `currency`, `active`, `sort_order` | methods are deactivated, not deleted |

## Admin — community, stats, settings, leaks

| Method & path | Input | Output |
|---|---|---|
| `GET /admin/questions` | `status?` | paginated, with the asker |
| `POST /admin/questions/{id}/star` | — | toggles `new`↔`starred` |
| `POST /admin/questions/{id}/answer` | `answer?`, `answered_in_post_id?` (at least one) | question marked answered |
| `GET /admin/stats/listening` | — | per episode: `started, completed (≥ 90 %), completion_percent, average_listened_seconds` |
| `GET /admin/settings` | — | all known settings (null when unset) |
| `PUT /admin/settings` | any subset of `social_links[{title,url}]`, `support_items[{title,kind,value,network?,note?}]`, `page_about`, `page_terms`, `page_install_guide` (HTML, sanitized), `maintenance`, `maintenance_message` | all settings |
| `POST /admin/leak-checks/text` | `text` | `{result: found\|ambiguous\|not_found, users[]}` — reads the invisible marks |
| `POST /admin/leak-checks/audio` | multipart `audio` | same, via `audiowmark get` |
| `GET /admin/leak-checks` | — | history |

Every admin write is recorded in `admin_logs`.

---

## Background work

Run these locally (see `docs/progress.md` for the exact commands):

- **Queue worker** for `watermark-priority`, `watermark`, `default` — watermark builds, mirror copies, emails.
- **Scheduler** (`schedule:work` locally, cron `schedule:run` in production): `periscope:build-watermarks` (hourly; copies for premium episodes going live within 3 h), `periscope:cleanup-watermarks` (daily; removes copies older than 7 days — they are rebuilt on demand), `periscope:notify-new-episodes` (every 5 min).
