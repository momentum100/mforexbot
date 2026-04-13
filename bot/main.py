"""Entry point for a single bot instance.

Usage:
    python main.py --bot-id=1
"""

import argparse
import asyncio
import logging
import os
import sys

from dotenv import load_dotenv
from aiogram import Bot, Dispatcher, types
from aiogram.client.default import DefaultBotProperties
from aiogram.enums import ParseMode

from db import Database
from i18n import TranslationService
from notifier import AdminNotifier
from handlers import start, menu, instruction, language, admin

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="M-Bot-Forex Telegram bot")
    parser.add_argument("--bot-id", type=int, required=True, help="Bot ID from bots table")
    return parser.parse_args()


async def set_bot_commands(bot: Bot) -> None:
    """Register bot commands menu visible to all users."""
    commands = [
        types.BotCommand(command="start", description="Start the bot"),
        types.BotCommand(command="language", description="Change language"),
        types.BotCommand(command="support", description="Contact support"),
        types.BotCommand(command="signal", description="Get trading signal"),
    ]
    await bot.set_my_commands(commands)


async def main() -> None:
    args = parse_args()
    bot_id: int = args.bot_id

    # Load .env from project root (one level up from bot/)
    env_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".env")
    load_dotenv(env_path)

    # Connect to DB
    try:
        db = Database(
            bot_id=bot_id,
            host=os.environ["DB_HOST"],
            port=int(os.environ.get("DB_PORT", 3306)),
            user=os.environ["DB_USER"],
            password=os.environ["DB_PASS"],
            database=os.environ["DB_NAME"],
        )
    except Exception as e:
        logger.critical("Database connection failed: %s", e)
        sys.exit(1)

    # Load bot config
    bot_config = db.get_bot()
    if not bot_config:
        logger.critical("Bot with id=%d not found in database", bot_id)
        sys.exit(1)

    token = bot_config["token"]
    logger.info("Starting bot '%s' (id=%d)", bot_config["name"], bot_id)

    # Initialize services
    bot = Bot(token=token, default=DefaultBotProperties(parse_mode=ParseMode.HTML))
    i18n = TranslationService(db)
    notifier = AdminNotifier(bot, db)

    # Set up dispatcher with dependency injection via workflow_data
    dp = Dispatcher()
    dp.workflow_data.update({
        "db": db,
        "i18n": i18n,
        "notifier": notifier,
        "bot_config": bot_config,
    })

    # Register routers
    dp.include_router(start.router)
    dp.include_router(language.router)
    dp.include_router(menu.router)
    dp.include_router(instruction.router)
    dp.include_router(admin.router)

    # Global error handler
    @dp.errors()
    async def on_error(event: types.ErrorEvent, db: Database, i18n: TranslationService, notifier: AdminNotifier):
        logger.error("Unhandled error: %s", event.exception, exc_info=event.exception)
        try:
            await notifier.notify(
                f"Unhandled error: {event.exception}",
                level="error",
            )
        except Exception:
            pass

    # Set commands and notify admins
    await set_bot_commands(bot)
    await notifier.notify(f"Bot started (bot_id={bot_id})", level="info")

    # Start polling
    try:
        await dp.start_polling(bot)
    finally:
        await notifier.notify("Bot stopped", level="info")
        await bot.session.close()


if __name__ == "__main__":
    asyncio.run(main())
