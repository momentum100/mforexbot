# Multi-Bot Launcher

Status: implemented 2026-04-15.

## Goal & Motivation

M-Bot-Forex is multi-tenant: one codebase, one MySQL, N Telegram bots, each
with its own token, translations and users. The original deployment ran one
container per bot (`python main.py --bot-id=1`). That was fine for a single
bot but does not scale:

- New bot = new compose service + redeploy.
- No runtime control from the admin panel.
- Admin has to SSH to toggle a bot on/off.

The launcher gives us:

1. **One container, many bots.** A single `python launcher.py` process polls
   Telegram for every active bot concurrently.
2. **Admin-controlled active/inactive** flag on the `bots` row.
3. **Remote restart button** in the admin panel — clicking it writes a
   timestamp to the `system` table, the launcher notices within ~5 s,
   performs a clean shutdown (`sys.exit(0)`), and Docker's
   `restart: unless-stopped` brings it straight back, at which point the
   new set of active bots is loaded.

## Schema additions

Migration `018_multi_bot_launcher.sql` (single combined migration):

```sql
ALTER TABLE bots
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS system (
    id TINYINT NOT NULL PRIMARY KEY DEFAULT 1,
    restart_requested_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT system_singleton CHECK (id = 1)
);
INSERT IGNORE INTO system (id, restart_requested_at) VALUES (1, NULL);
```

Single-row table pattern: a `CHECK (id = 1)` constraint plus an
`INSERT IGNORE` seed guarantees there is exactly one row. The launcher
reads/writes that row only.

`bots.is_active` defaults to `1` so existing rows keep running.

Translations for the new admin UI strings live in
`019_seed_launcher_translations.sql` (EN + RU).

## Launcher flow

File: `bot/launcher.py`.

At startup:

1. Load `.env`, connect to MySQL using a throw-away `Database(bot_id=0)` just
   for the "which bots are active?" query.
2. `SELECT id FROM bots WHERE is_active = 1` — capture the list.
3. Record `launcher_start_time = datetime.utcnow()`.
4. For each active bot id, build a full per-bot stack:
   - `Database(bot_id)` (its own connection pool)
   - `TranslationService(db)`
   - `aiogram.Bot(token, …)`
   - `aiogram.Dispatcher()` with the same routers as `bot/main.py`
   - `dp.workflow_data.update({db, i18n, notifier, bot_config})`
   - `await set_bot_commands(bot)`
   - `await notifier.notify("Bot started …")`
5. Launch every dispatcher concurrently:
   `await asyncio.gather(*(dp.start_polling(bot) for dp, bot in stacks), restart_watcher(...))`.

### Architecture choice: one Dispatcher per bot

aiogram 3's `Dispatcher.start_polling(*bots)` supports multiple bots on a
single dispatcher, but `workflow_data` is shared across bots — our handlers
expect per-bot `db`, `i18n`, `notifier`, `bot_config` injected as workflow
data. Reusing one dispatcher would require a Bot-keyed middleware to swap
DI on every update, which is awkward and fragile.

We therefore use **N dispatchers running concurrently via `asyncio.gather`**.
Each dispatcher owns its per-bot workflow_data. Routers are the same module
objects, which is safe because routers are declarative — they carry no
per-bot state.

### Restart watcher

A background task started alongside the dispatchers:

```python
async def restart_watcher(root_db: Database, launcher_start: datetime):
    while True:
        row = root_db.fetchone(
            "SELECT restart_requested_at FROM system WHERE id = 1"
        )
        ts = row and row["restart_requested_at"]
        if ts and ts > launcher_start:
            logger.info("Restart requested at %s — exiting", ts)
            sys.exit(0)
        await asyncio.sleep(5)
```

On `sys.exit(0)` aiogram tears down cleanly. Docker respawns the container
(`restart: unless-stopped`) and the new process sees the fresh
`is_active` state.

### Gotcha — toggling a bot off does **not** stop it live

Because the active list is read once at launcher start, toggling
`is_active = 0` in the admin UI has no immediate effect on a running bot.
It takes effect **on the next launcher start**, either by:

- clicking "Restart all bots" in the admin panel, or
- any other cause of container restart.

This is deliberate — simpler than hot-adding/removing dispatchers and
matches the "restart after admin change" pattern the user already uses for
config changes.

## Admin UI

### Bots list page (`/admin/bots`)

- A "🔄 Restart all bots" button in the top-right of the page. Submits a
  CSRF-protected POST to `/admin/bots/restart` with a `confirm()` JS
  dialog.
- Every row shows a status badge (🟢 Active / ⚪ Inactive).
- Bot edit form has a checkbox "Active" bound to `is_active`.

Controller: `AdminController::restartBots` runs

```sql
UPDATE system SET restart_requested_at = NOW() WHERE id = 1;
```

then reroutes back to `/admin/bots` with a flash message
("Restart requested — bots will reload within 10 seconds.").

Flash message implementation uses `$_SESSION['flash']` read and cleared
by the layout.

## Adding a new bot

1. `/admin/bots` → fill form (name, token, …), leave "Active" checked.
2. Click **Save Bot**.
3. Click **Restart all bots**.
4. Within ~10 seconds the new bot is polling.

No code changes, no container edits.

## Turning a bot off

1. Edit bot, uncheck "Active", Save.
2. Click **Restart all bots**.

## Backwards compatibility

- `python main.py --bot-id=N` still works unchanged for local dev /
  debugging a single bot. The launcher is additive.
- `docker-compose.yml`'s `bot` service CMD is switched from
  `python main.py --bot-id=1` to `python launcher.py`.

## Operational notes

- Logs are prefixed with `[bot_id=N name]` so multi-bot output is still
  greppable.
- Per-bot connection pool size is 5 (unchanged from single-bot); with N
  bots the launcher holds 5·N pooled connections. Bump MySQL
  `max_connections` if N grows beyond ~10.
- If a single bot's `start_polling` raises, `asyncio.gather` cancels the
  rest. This is intentional: Docker restarts and we retry cleanly.
