"""Database access layer. One Database instance per bot process."""

import logging
from typing import Any, Optional

import mysql.connector
from mysql.connector import pooling

from constants import UserStatus

logger = logging.getLogger(__name__)


class Database:
    """Thin wrapper around mysql-connector connection pool.

    Every query is scoped to *bot_id* so a single DB can serve many bots.
    """

    def __init__(self, bot_id: int, host: str, port: int, user: str, password: str, database: str):
        self.bot_id = bot_id
        self._pool = pooling.MySQLConnectionPool(
            pool_name=f"bot_{bot_id}",
            pool_size=5,
            host=host,
            port=port,
            user=user,
            password=password,
            database=database,
            charset="utf8mb4",
            collation="utf8mb4_unicode_ci",
            autocommit=True,
        )

    # ------------------------------------------------------------------
    # Low-level helpers
    # ------------------------------------------------------------------

    def _get_conn(self):
        return self._pool.get_connection()

    def fetchone(self, query: str, params: tuple = ()) -> Optional[dict]:
        conn = self._get_conn()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(query, params)
            return cur.fetchone()
        finally:
            conn.close()

    def fetchall(self, query: str, params: tuple = ()) -> list[dict]:
        conn = self._get_conn()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute(query, params)
            return cur.fetchall()
        finally:
            conn.close()

    def execute(self, query: str, params: tuple = ()) -> int:
        conn = self._get_conn()
        try:
            cur = conn.cursor()
            cur.execute(query, params)
            return cur.rowcount
        finally:
            conn.close()

    # ------------------------------------------------------------------
    # Bot config
    # ------------------------------------------------------------------

    def get_bot(self) -> Optional[dict]:
        return self.fetchone("SELECT * FROM bots WHERE id = %s", (self.bot_id,))

    def update_referral_url(self, template: str) -> None:
        self.execute(
            "UPDATE bots SET referral_url_template = %s, updated_at = NOW() WHERE id = %s",
            (template, self.bot_id),
        )

    # ------------------------------------------------------------------
    # Users
    # ------------------------------------------------------------------

    def get_user(self, telegram_id: int) -> Optional[dict]:
        return self.fetchone(
            "SELECT * FROM users WHERE bot_id = %s AND telegram_id = %s",
            (self.bot_id, telegram_id),
        )

    def upsert_user(
        self,
        telegram_id: int,
        username: Optional[str] = None,
        first_name: Optional[str] = None,
        last_name: Optional[str] = None,
        bio: Optional[str] = None,
        lang_code: str = "en",
    ) -> dict:
        """Insert or update a user, then return the row."""
        self.execute(
            """
            INSERT INTO users (bot_id, telegram_id, username, first_name, last_name, bio, lang_code)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                username   = VALUES(username),
                first_name = VALUES(first_name),
                last_name  = VALUES(last_name),
                bio        = VALUES(bio),
                updated_at = NOW()
            """,
            (self.bot_id, telegram_id, username, first_name, last_name, bio, lang_code),
        )
        return self.get_user(telegram_id)

    def set_start_param_if_empty(self, telegram_id: int, start_param: str) -> None:
        """Set start_param only if it's currently NULL (first /start capture)."""
        self.execute(
            """
            UPDATE users
            SET start_param = %s, updated_at = NOW()
            WHERE bot_id = %s AND telegram_id = %s AND start_param IS NULL
            """,
            (start_param, self.bot_id, telegram_id),
        )

    def update_user_lang(self, telegram_id: int, lang_code: str) -> None:
        self.execute(
            "UPDATE users SET lang_code = %s, updated_at = NOW() WHERE bot_id = %s AND telegram_id = %s",
            (lang_code, self.bot_id, telegram_id),
        )

    def set_user_registered(self, telegram_id: int) -> None:
        """Upgrade user status from NEW to REGISTERED.

        Only upgrades — never downgrades from DEPOSITED.  Idempotent.
        """
        self.execute(
            """
            UPDATE users
            SET status = %s, updated_at = NOW()
            WHERE bot_id = %s AND telegram_id = %s AND status = %s
            """,
            (UserStatus.REGISTERED, self.bot_id, telegram_id, UserStatus.NEW),
        )

    def set_password_passed(self, telegram_id: int) -> None:
        """Flag the user as having entered bots.access_password correctly.

        Idempotent — safe to call repeatedly. Scoped by bot_id, so the same
        Telegram user on a different bot must enter that bot's password
        separately.
        """
        self.execute(
            """
            UPDATE users
            SET password_passed = 1, updated_at = NOW()
            WHERE bot_id = %s AND telegram_id = %s
            """,
            (self.bot_id, telegram_id),
        )

    def has_postback_event(self, telegram_id: int, event_type: str) -> bool:
        row = self.fetchone(
            "SELECT 1 FROM postback_events "
            "WHERE bot_id = %s AND telegram_id = %s AND event_type = %s AND auth_status = 'ok' "
            "LIMIT 1",
            (self.bot_id, telegram_id, event_type),
        )
        return row is not None

    def get_admin_ids(self) -> list[int]:
        rows = self.fetchall(
            "SELECT telegram_id FROM users WHERE bot_id = %s AND is_admin = 1",
            (self.bot_id,),
        )
        return [r["telegram_id"] for r in rows]

    def get_users_by_status(self, status_filter: Optional[str] = None) -> list[dict]:
        """Return users for broadcast.

        status_filter: None = all, REGISTERED = registered+deposited,
        DEPOSITED = deposited only.
        """
        if status_filter == UserStatus.DEPOSITED:
            return self.fetchall(
                "SELECT telegram_id FROM users WHERE bot_id = %s AND status = %s",
                (self.bot_id, UserStatus.DEPOSITED),
            )
        if status_filter == UserStatus.REGISTERED:
            return self.fetchall(
                "SELECT telegram_id FROM users WHERE bot_id = %s AND status IN (%s, %s)",
                (self.bot_id, UserStatus.REGISTERED, UserStatus.DEPOSITED),
            )
        return self.fetchall(
            "SELECT telegram_id FROM users WHERE bot_id = %s",
            (self.bot_id,),
        )

    # ------------------------------------------------------------------
    # Statistics
    # ------------------------------------------------------------------

    def count_users_total(self) -> int:
        row = self.fetchone("SELECT COUNT(*) AS cnt FROM users WHERE bot_id = %s", (self.bot_id,))
        return row["cnt"] if row else 0

    def count_users_registered(self) -> int:
        row = self.fetchone(
            "SELECT COUNT(*) AS cnt FROM users WHERE bot_id = %s AND status IN (%s, %s)",
            (self.bot_id, UserStatus.REGISTERED, UserStatus.DEPOSITED),
        )
        return row["cnt"] if row else 0

    def count_users_deposited(self) -> int:
        row = self.fetchone(
            "SELECT COUNT(*) AS cnt FROM users WHERE bot_id = %s AND status = %s",
            (self.bot_id, UserStatus.DEPOSITED),
        )
        return row["cnt"] if row else 0

    # ------------------------------------------------------------------
    # Translations
    # ------------------------------------------------------------------

    def get_all_translations(self) -> list[dict]:
        """Load all translations relevant to this bot (bot-specific + base).

        Returns rows ordered so bot-specific overrides come first.
        """
        return self.fetchall(
            """
            SELECT `key`, lang_code, value, bot_id
            FROM translations
            WHERE bot_id = %s OR bot_id IS NULL
            ORDER BY bot_id DESC
            """,
            (self.bot_id,),
        )

    # ------------------------------------------------------------------
    # Languages
    # ------------------------------------------------------------------

    def get_languages(self) -> list[dict]:
        return self.fetchall("SELECT * FROM languages ORDER BY sort_order")

    def get_language_codes(self) -> set[str]:
        rows = self.fetchall("SELECT code FROM languages")
        return {r["code"] for r in rows}
