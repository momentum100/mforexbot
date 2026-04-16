# M-Bot-Forex — Multi-Bot Telegram Platform

## Project Overview
Multi-tenant Telegram bot platform for forex trading signal analysis. Supports multiple bots from a single codebase, each with its own users, translations, and config. Each bot has a menu-driven interface and a Telegram Mini Web App.

## Tech Stack
- **Language:** Python 3.x (bot), PHP 8.x (web)
- **Virtual env:** venv
- **Telegram framework:** aiogram 3+
- **Database:** MySQL (mysql-connector-python / PDO)
- **PHP framework:** Fat-Free Framework (F3)
- **Web server:** Nginx + PHP-FPM (Docker containers)
- **Containerization:** Docker (with network to MySQL)
- **CI/CD:** GitHub Actions
- **Web App UI:** Tailwind CSS + shadcn/ui styled components
- **Architecture:** SOLID + KISS principles

## Multi-Tenant Architecture
- All data is scoped by `bot_id` — every table has a `bot_id` column
- `bots` table holds bot name + API token
- Python bot process starts with `bot_id` parameter: `python main.py --bot-id=1`
- Each bot instance runs as a separate process
- Web App and Admin Panel resolve `bot_id` from URL or session context

## Global settings (exception to multi-tenant rule)
- `settings` table (migration 017) is **truly global** — no `bot_id` column.
- Key-value store for platform-wide config that doesn't sensibly scale per bot.
- Current keys: `postback_base_url` (used to render the full postback URL for each bot's partner cabinet in `/admin/bots` edit form).
- Edited in `/admin/settings`. Add new keys via plain `INSERT`; no schema change needed.

## Architecture Guidelines
- **S** — Single Responsibility: each module/class does one thing
- **O** — Open/Closed: extend via new classes, not modifying existing
- **L** — Liskov Substitution: subtypes must be substitutable
- **I** — Interface Segregation: small, focused interfaces
- **D** — Dependency Inversion: depend on abstractions, not concretions
- **KISS** — Keep it simple. No over-engineering. If it doesn't need a pattern, don't use one.

## Environment
- Config loaded from `.env` (never commit this file)
- DB credentials: `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`

## Localization (i18n)
- All user-facing texts stored in MySQL, NOT hardcoded
- Two-tier translation system:
  - **Base translations** (`bot_id = NULL`) — shared defaults for all bots
  - **Bot overrides** (`bot_id = X`) — per-bot customization, takes priority
- Resolution: `SELECT ... WHERE key = ? AND lang_code = ? AND bot_id = ? UNION SELECT ... WHERE bot_id IS NULL ... LIMIT 1`
- Admin panel manages both base and per-bot translations
- Supported languages: EN, RU, ES, AR, PT, TR, HI, UZ, AZ, TG, KO

## Database Migrations
- Stored in `migrations/` folder
- Numbered sequentially: `001_initial_schema.sql`, `002_seed_bot.sql`, etc.
- Run manually in order against MySQL
- Each migration is idempotent where possible (`IF NOT EXISTS`)

## Development Workflow
1. Document first — define flows, menus, schemas in docs
2. Test — write tests for expected behavior
3. Code — implement against docs and tests

## Agent Delegation Rule
- All code writing, code fixes, documentation edits, and migration creation MUST be delegated to a subagent with `model: "opus"` explicitly set.
- Read-only exploration (grep, review, status reports) may run in the main loop or default agents.
