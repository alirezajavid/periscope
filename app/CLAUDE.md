# app/ — user PWA (app.periscopepod.com)

Frontend conventions: @../docs/frontend.md

## Specific to this app

- Quasar **PWA mode** with a custom Service Worker (`src-pwa/custom-service-worker.js`, Workbox InjectManifest).
- Layout: bottom tab bar (home, search, questions, account) with the floating mini-player above it — see the Figma prototype linked in `docs/reference.md` §۹.
- **Player** (`usePlayerStore`): background playback, Media Session API, speed 1×–2×, sleep timer, ±15 s, resume from `playback_progress`; report progress to the API periodically and on pause.
- **Silent offline cache**: ~30 s after playback starts, the SW fetches the whole personalized file into Cache Storage at low priority (RangeRequestsPlugin for seeking). Before playing from cache, check subscription status; allow ~7 days offline since the last online check. Clear the cache on logout or when the subscription ends. Call `navigator.storage.persist()` (iOS).
- Never cache API responses containing premium `body` in the SW beyond what the offline rules allow.
- Handle these API states with clear Persian screens: 401 (re-login, incl. browser/OS change), 402/403 (subscription needed → plans), "playing on another device", "file being prepared".
