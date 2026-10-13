---
name: new-endpoint
description: Add a new API endpoint to api/ (user or admin) following Periscope conventions — route, FormRequest, controller, Resource, policy, tests, and the frontend call.
---

# Adding an endpoint

1. **Decide the side**: user (`routes/api.php`, `Controllers/Api`) or admin (`routes/admin.php`, `Controllers/Admin`, admin middleware stack, `AdminLog::record` on writes).
2. **Route**: RESTful name and verb; numeric IDs (`/posts/{post}`), route-model binding.
3. **FormRequest** (writes only): `php artisan make:request {Api|Admin}/VerbNounRequest`. Put authorization in `authorize()` (delegate to a Policy) and all validation in `rules()`. Messages come from `lang/fa`.
4. **Controller method**: FormRequest → model or Service → Resource. If you need more than ~10 lines of logic, it belongs in the relevant Service (Watermark, Payment, Subscription, TextFingerprint) — don't create a new Service without asking.
5. **Resource**: never return a model directly. For posts, `body` and audio URL are included only when the Policy allows; text goes through `TextFingerprint` for the current user.
6. **Tests**: if the endpoint touches auth, premium access, payments/subscriptions, watermarking or admin authorization, add Pest feature tests covering the allowed **and** the denied case.
7. **Frontend**: add the call in the relevant Pinia store using `@shared/api/client.js`; add any new UI text to `src/i18n/fa.json`.
8. **Checks**: `./vendor/bin/pint --test && ./vendor/bin/phpstan analyse && php artisan test`, plus `npm run lint` in the app you touched.
