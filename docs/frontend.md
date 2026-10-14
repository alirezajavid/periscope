# Frontend conventions (app/ and admin/)

Both apps: Quasar 2 (Vite), Vue 3, **JavaScript** (no TypeScript), `<script setup>`, Pinia, vue-i18n. RTL Persian UI.

## Structure (each app)

```
src/
  pages/        one file per route, named XxxPage.vue
  layouts/      MainLayout.vue (+ AuthLayout.vue)
  components/   reusable pieces, PascalCase, grouped by feature folder when > ~5 files
  stores/       Pinia setup stores: useAuthStore, usePlayerStore, …  (one per domain)
  composables/  useXxx.js — reusable logic that isn't global state
  boot/         axios.js, i18n.js (Quasar boot files)
  i18n/fa.json  ALL user-visible text
  css/          app.scss, quasar.variables.scss (palette lives here)
```

## Rules

- **RTL:** this Quasar version has no `build.rtl` and its CSS hard-codes `direction: ltr`, so `app.scss` sets `direction: rtl` on `html, body, #q-app` (and `<html lang="fa" dir="rtl">` in `index.html`). Use logical CSS properties (`inline-start`, `margin-inline`, …) in custom styles.
- **Messages (vue-i18n):** `@` starts a linked message — write a literal one as `{'@'}`.

- **Font:** B Yekan+ (`'BYekan'`, regular 400 and bold 700) in both apps — files in `src/assets/fonts`, `@font-face` in `app.scss`, family in `$typography-font-family`; fallback `Tahoma, sans-serif`. Don't set another `font-family` anywhere (use `inherit`). The Roboto extra is removed.
- **app/** follows the prototype images in `docs/design/` (390px-wide mobile frames). **admin/** uses plain, standard Quasar (QLayout + QDrawer, QHeader, QTable, QForm, default colors) — Persian and RTL, no custom design.
- Page → Pinia setup store → `@shared/api/client.js`: pages never call axios directly. Loading / empty / error states use shared components.

- Components: `<script setup>`, `defineProps` with types/defaults, `defineEmits`. Keep pages thin — data fetching in stores or composables.
- **No hard-coded Persian strings** in templates or scripts: `t('player.speed')` / `$t(...)`. Keys are English, nested by feature.
- API calls only through the shared client (`@shared/api/client.js`); never create another axios instance. Sanctum SPA cookies: `withCredentials: true` and call `/sanctum/csrf-cookie` before the first write.
- Dates: `@shared/utils/date.js` (uses built-in `Intl.DateTimeFormat('fa-IR-u-ca-persian')`) — no date library.
- Numbers/prices: `@shared/utils/number.js` (Persian digits via `Intl.NumberFormat('fa-IR')`).
- Styling: Quasar components and utility classes first; custom SCSS only when needed. Colors only through Quasar brand variables (`$primary` = `#4CC3EF`, dark background `#0B1033`, …) — never literal hex in components.
- Post body HTML: `v-html` only inside `<div class="prose" dir="rtl">`, only for `body` that came from the API (already purified). Never `v-html` anything else.
- Video: build the `<iframe>` from the server-provided URL; never render raw embed code.
- Lint/format: ESLint + Prettier with the Quasar defaults; formatting runs automatically after edits.

## shared/

Plain ES modules, framework-light, imported via the `@shared` alias (configured in each app's `quasar.config.js`):

```
shared/
  api/client.js       axios instance factory (baseURL from env, credentials, CSRF, 401/419 handling hook)
  utils/date.js       Jalali formatting
  utils/number.js     Persian digits, price formatting per currency
```

Only put code here when **both** apps use it. No Vue components in `shared/` unless both apps need the identical component.

## Commands (inside app/ or admin/)

```bash
npx quasar dev            # app/: npx quasar dev -m pwa
npm run lint
npx quasar build          # app/: npx quasar build -m pwa
```
