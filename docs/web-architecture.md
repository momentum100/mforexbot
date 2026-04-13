# Web Architecture

## Overview
Single domain serves both the Telegram Mini Web App and the Admin Panel via different URL paths.

```
https://domain.com/
├── /app/{bot_id}/         → Telegram Mini Web App (signal generator, scoped to bot)
└── /admin/                → Admin Panel (manage bots, translations, admin users)
```

## Stack
- **Nginx** — web server, static files, routing (behind external proxy)
- **PHP-FPM** — Fat-Free Framework (F3)
- **MySQL** — shared database (`mforexbot`)
- **Docker** — all services in containers on same network

## Docker Compose Layout
```
services:
  nginx:
    - ports: 80, 443
    - routes /app/ → php-fpm
    - routes /admin/ → php-fpm
  php-fpm:
    - PHP 8.x + F3 framework
    - PDO MySQL
  bot:
    - Python 3.x + aiogram 3+
    - runs: python main.py --bot-id=1
    - one container per bot instance
    - restart: unless-stopped
  mysql:
    - external, host: 148.113.138.32
    - or same docker network if local
```

All services share a docker network with access to MySQL.

**Note:** For multiple bots, spin up multiple `bot` containers with different `--bot-id` args. Can use docker-compose `deploy.replicas` or separate service definitions per bot.

---

## Admin Panel (`/admin/`)

### Authentication
- Separate `admin_users` table (not Telegram users)
- Username + password (hashed with `password_hash()`)
- Session-based auth
- Login page with username + password fields

### Admin Users Table: `admin_users`

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| username | VARCHAR(255) | Login username (unique) |
| password | VARCHAR(255) | Bcrypt hashed password |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### Pages

#### 1. Login (`/admin/login`)
- Username + password fields
- Submit button
- On success → redirect to `/admin/bots`
- On failure → error message

#### 2. Bots Manager (`/admin/bots`)
- List all bots with name, token (masked), user count
- Add new bot
- Edit bot name/token
- Select bot → goes to bot-specific management

#### 3. Translations Manager (`/admin/translations?bot_id=X`)

**Bot selector:** Dropdown to pick bot (or "Base/Default" for shared translations)

**Header:** Language tab selector (EN | RU | ES | AR | PT | TR | HI | UZ | AZ | TG | KO)

**Table view:**

| Key | Base Value | Bot Override | Actions |
|-----|-----------|--------------|---------|
| main_menu.title | Main Menu | — | ✏️ Override |
| signal.buy | Buy | Купить! | ✏️ Edit / 🗑️ Reset |
| ... | ... | ... | ... |

**Features:**
- Filter/search by key name
- Select language tab to view/edit values for that language
- Shows base value alongside bot-specific override
- Inline edit for overrides
- "Reset" removes bot override, falls back to base
- Add new base key (creates entry for all languages with empty values)
- Visual indicator for missing translations (empty values highlighted)

**Workflow:**
1. Select bot (or "Base") + language tab
2. Table shows all keys with base values and bot overrides
3. Edit override → `INSERT/UPDATE translations SET value = ? WHERE key = ? AND lang_code = ? AND bot_id = ?`
4. Reset override → `DELETE FROM translations WHERE key = ? AND lang_code = ? AND bot_id = ?`

#### 4. Users (`/admin/users?bot_id=X`) — future
- View bot users, stats

---

## Telegram Mini Web App (`/app/{bot_id}/`)

- `bot_id` in URL scopes all data to that bot
- Serves the signal generator UI (Screen 4 from bot-flow.md)
- Tailwind CSS + shadcn/ui styled
- Communicates with PHP backend API

### API Endpoints (PHP/F3 backend)

All endpoints scoped by `bot_id`:

```
GET  /app/{bot_id}/api/translations?lang=ru  → translations (bot override → base fallback)
GET  /app/{bot_id}/api/pairs?type=forex      → currency pairs list (forex|otc)
POST /app/{bot_id}/api/signal                → generate signal (STUB: random values for now)
     body: { pair: "EUR/USD", expiration: "1m", type: "forex" }
     response: { direction: "buy|sell", confidence: "low|medium|high", timestamp: "..." }
     NOTE: Current implementation returns random direction + confidence. Real logic TBD.
```

### Postback Route (Affiliate Status Updates)

```
GET /postback?user_id={telegram_id}&status={registered|deposited}&bot_id={bot_id}&secret={secret}
```

- Called by external affiliate system when user registers or deposits
- `{user_id}` = Telegram user ID (passed via referral link template)
- Updates `users.status` for matching `telegram_id` + `bot_id`
- `secret` = per-bot shared secret to authenticate the call (stored in `.env` or bots table)
- Returns `200 OK` on success, `401` on bad secret, `404` on user not found

**Referral link flow:**
1. Bot generates referral URL from `bots.referral_url_template` replacing `{user_id}` with `telegram_id`
2. User clicks referral link → goes to affiliate platform
3. User registers/deposits on affiliate platform
4. Affiliate platform calls postback URL → bot updates user status
5. Bot can optionally notify user or admin via `AdminNotifier`

---

### Translation Resolution (SQL)
```sql
SELECT COALESCE(
  (SELECT value FROM translations WHERE `key` = ? AND lang_code = ? AND bot_id = ?),
  (SELECT value FROM translations WHERE `key` = ? AND lang_code = ? AND bot_id IS NULL),
  (SELECT value FROM translations WHERE `key` = ? AND lang_code = 'en' AND bot_id IS NULL)
) AS value;
```
Priority: bot override → base for language → base English fallback

---

## Security

### Web App API Authentication
- All `/app/{bot_id}/api/*` requests must include Telegram `initData` in header
- Server-side validation: verify HMAC-SHA256 hash using bot token as secret
- See: Telegram docs on validating `initData`
- F3 beforeroute filter on all `/app/*` routes to validate before handler runs
- Reject with `401` if hash invalid or expired (>5min)

### Admin Panel Security
- **CSRF protection:** F3 built-in CSRF token on all forms (`\Session` + hidden field)
- **Rate limiting:** F3 `\Web` throttle on `/admin/login` — max 5 attempts per minute per IP
- **Session expiry:** PHP session timeout 30 minutes of inactivity
- **Password storage:** `password_hash(PASSWORD_BCRYPT)` / `password_verify()`
- **Headers:** `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`

### Postback Security
- Shared secret per bot for postback authentication
- Validate `secret` param before processing any status update
- Log all postback calls for audit

### General
- All DB queries use PDO prepared statements (no raw interpolation)
- `.env` never committed — contains secrets
- Bot tokens stored in DB, not in code
