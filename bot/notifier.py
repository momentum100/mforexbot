"""AdminNotifier -- sends notifications to all bot admins. Never crashes."""

import logging

from aiogram import Bot

from db import Database

logger = logging.getLogger(__name__)

LEVEL_PREFIX = {
    "info": "\u2139\ufe0f",
    "warning": "\u26a0\ufe0f",
    "error": "\U0001f6a8",
}


class AdminNotifier:
    """Sends messages to all is_admin=1 users for the current bot."""

    def __init__(self, bot: Bot, db: Database):
        self._bot = bot
        self._db = db

    async def notify(self, message: str, level: str = "info") -> None:
        """Send *message* to every admin. Silently swallows errors."""
        prefix = LEVEL_PREFIX.get(level, "")
        text = f"{prefix} {message}" if prefix else message

        admin_ids = self._db.get_admin_ids()
        for tid in admin_ids:
            try:
                await self._bot.send_message(chat_id=tid, text=text)
            except Exception:
                logger.debug("Failed to notify admin %s", tid, exc_info=True)
