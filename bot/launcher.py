"""Multi-bot launcher.

Runs every active bot (bots.is_active = 1) concurrently inside a single
asyncio event loop. Each bot gets its own aiogram Dispatcher + Bot +
Database + TranslationService so existing handlers (which expect per-bot
workflow_data) keep working unchanged.

A background watcher polls the `system` table; when an admin clicks
"Restart all bots" in /admin/bots the timestamp there updates, the watcher
notices within ~5 s and calls sys.exit(0). Docker's restart policy
(`restart: unless-stopped`) brings the container back with the new set of
active bots.

Usage (inside Docker):  python launcher.py
Legacy single-bot mode: python main.py --bot-id=N  (still supported)
"""

import asyncio
import logging
import os
import sys
from datetime import datetime, timezone
from typing import Optional

from dotenv import load_dotenv
from aiogram import Bot, Dispatcher, types
from aiogram.client.default import DefaultBotProperties
from aiogram.enums import ParseMode

from db import Database
from i18n import TranslationService
from notifier import AdminNotifier
from handlers import start, menu, instruction, language, admin, password_gate, deposit_gate

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("launcher")

# Polling interval for the restart watcher (seconds).
RESTART_POLL_SECONDS = 5


# ---------------------------------------------------------------------------
# DB helpers that don't require a concrete bot_id
# ---------------------------------------------------------------------------

def _db_kwargs() -> dict:
    return dict(
        host=os.environ["DB_HOST"],
        port=int(os.environ.get("DB_PORT", 3306)),
        user=os.environ["DB_USER"],
        password=os.environ["DB_PASS"],
        database=os.environ["DB_NAME"],
    )


def load_active_bot_ids() -> list[int]:
    """Query which bot ids have is_active = 1."""
    # Use bot_id=0 purely as a handle; no bot-scoped queries are made here.
    db = Database(bot_id=0, **_db_kwargs())
    rows = db.fetchall("SELECT id FROM bots WHERE is_active = 1 ORDER BY id ASC")
    return [r["id"] for r in rows]


# ---------------------------------------------------------------------------
# Per-bot setup (mirrors bot/main.py)
# ---------------------------------------------------------------------------

async def _set_bot_commands(bot: Bot) -> None:
    commands = [
        types.BotCommand(command="start", description="Start the bot"),
        types.BotCommand(command="language", description="Change language"),
        types.BotCommand(command="support", description="Contact support"),
        types.BotCommand(command="signal", description="Get trading signal"),
    ]
    await bot.set_my_commands(commands)


async def build_stack(bot_id: int) -> Optional[tuple[Dispatcher, Bot, AdminNotifier, dict]]:
    """Instantiate Database, i18n, Bot, Dispatcher for one bot_id.

    Returns (dp, bot, notifier, bot_config) or None if the bot row is missing.
    """
    db = Database(bot_id=bot_id, **_db_kwargs())
    bot_config = db.get_bot()
    if not bot_config:
        logger.error("Bot id=%d not found in DB — skipping", bot_id)
        return None

    token = bot_config["token"]
    name = bot_config.get("name") or f"bot_{bot_id}"
    logger.info("[bot_id=%d %s] initializing", bot_id, name)

    bot = Bot(token=token, default=DefaultBotProperties(parse_mode=ParseMode.HTML))
    i18n = TranslationService(db)
    notifier = AdminNotifier(bot, db)

    dp = Dispatcher()
    dp.workflow_data.update({
        "db": db,
        "i18n": i18n,
        "notifier": notifier,
        "bot_config": bot_config,
    })

    # Same router registration order as bot/main.py.
    # IMPORTANT: handler modules expose build_router() factories — aiogram 3
    # Router instances are stateful (they remember their parent Dispatcher),
    # so a fresh Router must be built per Dispatcher. Using a module-level
    # singleton here would raise "Router is already attached" for bot #2+.
    dp.include_router(start.build_router())
    dp.include_router(language.build_router())
    dp.include_router(password_gate.build_router())
    dp.include_router(deposit_gate.build_router())
    dp.include_router(menu.build_router())
    dp.include_router(instruction.build_router())
    dp.include_router(admin.build_router())

    @dp.errors()
    async def on_error(
        event: types.ErrorEvent,
        db: Database = db,
        i18n: TranslationService = i18n,
        notifier: AdminNotifier = notifier,
    ):
        logger.error(
            "[bot_id=%d %s] unhandled error: %s",
            bot_id, name, event.exception, exc_info=event.exception,
        )
        try:
            await notifier.notify(f"Unhandled error: {event.exception}", level="error")
        except Exception:
            pass

    await _set_bot_commands(bot)
    try:
        await notifier.notify(f"Bot started (bot_id={bot_id})", level="info")
    except Exception:
        logger.debug("[bot_id=%d] start notify failed", bot_id, exc_info=True)

    return dp, bot, notifier, bot_config


# ---------------------------------------------------------------------------
# Restart watcher
# ---------------------------------------------------------------------------

async def restart_watcher(launcher_start: datetime) -> None:
    """Poll system.restart_requested_at; sys.exit on new request."""
    db = Database(bot_id=0, **_db_kwargs())
    logger.info("Restart watcher started (launcher_start=%s UTC)", launcher_start.isoformat())
    while True:
        try:
            row = db.fetchone("SELECT restart_requested_at FROM system WHERE id = 1")
            ts = row["restart_requested_at"] if row else None
            # MySQL DATETIME columns return as naive datetimes (server stores UTC).
            # Make them timezone-aware so we can safely compare with launcher_start
            # (which is aware UTC since the datetime.utcnow() -> now(tz=UTC) fix).
            if ts is not None and ts.tzinfo is None:
                ts = ts.replace(tzinfo=timezone.utc)
            if ts is not None and ts > launcher_start:
                logger.warning(
                    "Restart requested at %s — shutting down so Docker can respawn", ts
                )
                # Clean exit; Dispatcher.start_polling will be cancelled by the
                # cancel propagation from asyncio.gather when this task raises
                # SystemExit.
                sys.exit(0)
        except SystemExit:
            raise
        except Exception:
            logger.warning("Restart watcher poll failed", exc_info=True)
        await asyncio.sleep(RESTART_POLL_SECONDS)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

async def main() -> None:
    env_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".env")
    load_dotenv(env_path)

    try:
        active_ids = load_active_bot_ids()
    except Exception as e:
        logger.critical("Cannot load active bots from DB: %s", e)
        sys.exit(1)

    if not active_ids:
        logger.warning("No active bots (is_active=1). Idling; waiting for restart signal.")

    launcher_start = datetime.now(timezone.utc)

    stacks: list[tuple[Dispatcher, Bot, AdminNotifier, dict]] = []
    for bot_id in active_ids:
        try:
            built = await build_stack(bot_id)
        except Exception as e:
            logger.error("Failed to build stack for bot_id=%d: %s", bot_id, e, exc_info=True)
            continue
        if built is not None:
            stacks.append(built)

    logger.info("Launcher ready: %d bot(s) active", len(stacks))

    polling_tasks = [
        asyncio.create_task(dp.start_polling(bot), name=f"poll_bot_{cfg['id']}")
        for (dp, bot, _notifier, cfg) in stacks
    ]
    watcher_task = asyncio.create_task(restart_watcher(launcher_start), name="restart_watcher")

    try:
        await asyncio.gather(*polling_tasks, watcher_task)
    finally:
        for _dp, bot, notifier, cfg in stacks:
            try:
                await notifier.notify(f"Bot stopped (bot_id={cfg['id']})", level="info")
            except Exception:
                pass
            try:
                await bot.session.close()
            except Exception:
                pass


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except SystemExit:
        raise
    except KeyboardInterrupt:
        logger.info("Interrupted — exiting")
