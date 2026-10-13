# api/ — Laravel

Plain Laravel, the way the official docs do it. Only the heavy domains get a Service class.

## Structure

```
app/
  Http/Controllers/Api/      user-facing controllers   → routes/api.php        (/api/...)
  Http/Controllers/Admin/    admin controllers         → routes/admin.php      (/api/admin/...)
  Http/Requests/{Api,Admin}/ one FormRequest per write action (validation + authorize())
  Http/Resources/            API Resources — the only way models leave the API
  Http/Middleware/           e.g. EnsureSameDeviceFamily, EnsureAdminTwoFactor, AdminIdleTimeout
  Models/                    Eloquent models, casts, relations, small scopes
  Policies/                  authorization (e.g. PostPolicy@view decides premium access)
  Services/                  ONLY: Watermark/, Payment/, Subscription/, TextFingerprint/
  Jobs/                      queued work (GeneratePersonalizedAudio, MirrorFileToForeignHost, …)
  Enums/                     PHP backed enums for every ENUM column (PostStatus, PaymentStatus, …)
```

## Rules

- **Controllers stay thin**: validate via FormRequest → call model / Service → return Resource. No business logic, no queries longer than a line or two.
- **Services** hold multi-step domain logic (approve payment → create subscription → send mail; build watermarked file). Plain classes, injected via constructor; no interfaces unless there are two real implementations.
- No repositories, no DTO layer, no custom base controller.
- Every ENUM column has a backed enum in `app/Enums` and a cast on the model.
- Authorization in Policies / FormRequest `authorize()`, never `if` checks scattered in controllers.
- Admin routes: `auth:sanctum` + admin role + `EnsureAdminTwoFactor` + idle timeout; every admin write action calls `AdminLog::record(...)`.
- Times stored in UTC; API returns ISO 8601. Jalali conversion happens in the frontend only.
- Errors: Laravel's default JSON shape (`message`, `errors`). Translatable strings via `__()` with `lang/fa`.
- Lists: `->paginate()` / `->cursorPaginate()` wrapped in Resource collections. Eager-load to avoid N+1 (`Model::preventLazyLoading()` is on outside production).
- Files: `Storage` disks only (`local` private, `public` covers). Premium audio is never on a public disk; it is served through short-lived signed URLs (~2 h).
- Shelling out (audiowmark, ffmpeg): `Illuminate\Support\Facades\Process` with array arguments — never string-concatenated commands.
- Migrations: one table per migration, foreign keys with explicit `->constrained()->…OnDelete()`; never edit a merged migration.
- Config values come from `config/periscope.php` (which reads `.env`), never `env()` outside config files.

## Tests (Pest)

- Feature tests under `tests/Feature/{Auth,Content,Payments,Watermark,Admin}`; use factories and `RefreshDatabase` against the MySQL test DB `periscope_test`.
- Required for the sensitive areas listed in the root `CLAUDE.md`. For premium content, always assert both sides: subscriber sees `body`/audio URL, non-subscriber does **not**.
- Fake external effects: `Queue::fake()`, `Mail::fake()`, `Storage::fake()`, `Process::fake()` for audiowmark/ffmpeg.

## Commands

```bash
php artisan serve                     # local API
php artisan queue:work redis          # local worker
php artisan test                      # Pest
./vendor/bin/pint                     # format (runs automatically via hook)
./vendor/bin/phpstan analyse          # Larastan level 5
```
