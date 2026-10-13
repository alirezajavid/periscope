# admin/ — admin panel (admin.periscopepod.com)

Frontend conventions: @../docs/frontend.md

## Specific to this app

- Quasar **SPA mode** — no PWA, no service worker, no offline cache.
- Desktop-first layout with the sidebar from the Figma "Admin Panel" page (`docs/reference.md` §۹); must still be usable on a phone.
- Login → mandatory TOTP step (Fortify). The API enforces a 30-minute idle timeout; on 401/419 send the admin back to login without losing unsaved form input where practical.
- Post editor: `QEditor` with only the toolbar the API accepts (bold, italic, H2, H3, bullet/numbered list, quote, link, clear formatting). No images in the body.
- Every destructive action (block user, reject payment, delete post) needs a confirm dialog that states the consequence in Persian.
- Tables: `QTable` with server-side pagination/filtering via the API.
