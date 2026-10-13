# Periscope

Subscription PWA and admin panel for the Periscope podcast.

| | |
|---|---|
| `api/` | Laravel API |
| `app/` | Quasar PWA — app.periscopepod.com |
| `admin/` | Quasar admin panel — admin.periscopepod.com |
| `shared/` | JS shared by both apps |
| `docs/reference.md` | Product & technical reference (Persian) |
| `docs/setup.md` | First-time setup |
| `CLAUDE.md` | Working rules (for people and for Claude Code) |

Deploy: `cp deploy.env.example deploy.env`, fill it in, then `./deploy.sh [api|app|admin|all]` from an up-to-date `main`.
