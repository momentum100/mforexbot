"""Database access layer. One Database instance per bot process."""

import logging
from typing import Any, Optional

import mysql.connector
from mysql.connector import pooling

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

    def update_user_lang(self, telegram_id: int, lang_code: str) -> None:
        self.execute(
            "UPDATE users SET lang_code = %s, updated_at = NOW() WHERE bot_id = %s AND telegram_id = %s",
            (lang_code, self.bot_id, telegram_id),
        )

    def get_admin_ids(self) -> list[int]:
        rows = self.fetchall(
            "SELECT telegram_id FROM users WHERE bot_id = %s AND is_admin = 1",
            (self.bot_id,),
        )
        return [r["telegram_id"] for r in rows]

    def get_users_by_status(self, status_filter: Optional[str] = None) -> list[dict]:
        """Return users for broadcast.

        status_filter: None = all, 'registered' = registered+deposited, 'deposited' = deposited only.
        """
        if status_filter == "deposited":
            return self.fetchall(
                "SELECT telegram_id FROM users WHERE bot_id = %s AND status = 'deposited'",
                (self.bot_id,),
            )
        if status_filter == "registered":
            return self.fetchall(
                "SELECT telegram_id FROM users WHERE bot_id = %s AND status IN ('registered', 'deposited')",
                (self.bot_id,),
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
            "SELECT COUNT(*) AS cnt FROM users WHERE bot_id = %s AND status IN ('registered', 'deposited')",
            (self.bot_id,),
        )
        return row["cnt"] if row else 0

    def count_users_deposited(self) -> int:
        row = self.fetchone(
            "SELECT COUNT(*) AS cnt FROM users WHERE bot_id = %s AND status = 'deposited'",
            (self.bot_id,),
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
