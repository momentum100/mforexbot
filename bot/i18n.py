"""Translation service with two-tier resolution + English fallback."""

import logging
from typing import Optional

from db import Database

logger = logging.getLogger(__name__)


class TranslationService:
    """Loads all translations into memory for fast lookup.

    Resolution order:
      1. Bot override  (bot_id = X, lang_code, key)
      2. Base default   (bot_id = NULL, lang_code, key)
      3. English fallback (bot_id = NULL, 'en', key)
      4. Return the key itself (last resort)
    """

    def __init__(self, db: Database):
        self._db = db
        # _cache[(key, lang_code, bot_id_or_none)] = value
        self._cache: dict[tuple[str, str, Optional[int]], str] = {}
        self.reload()

    def reload(self) -> None:
        """(Re)load translations from DB into memory cache."""
        rows = self._db.get_all_translations()
        self._cache.clear()
        for row in rows:
            cache_key = (row["key"], row["lang_code"], row["bot_id"])
            self._cache[cache_key] = row["value"]
        logger.info("Loaded %d translation rows", len(self._cache))

    def t(self, key: str, lang_code: str = "en", **kwargs) -> str:
        """Resolve a translation key with optional placeholder formatting."""
        bot_id = self._db.bot_id

        # 1. Bot override
        val = self._cache.get((key, lang_code, bot_id))
        # 2. Base default (same language)
        if val is None:
            val = self._cache.get((key, lang_code, None))
        # 3. English fallback
        if val is None and lang_code != "en":
            val = self._cache.get((key, "en", bot_id))
            if val is None:
                val = self._cache.get((key, "en", None))
        # 4. Key itself
        if val is None:
            logger.warning("Missing translation: key=%s lang=%s bot_id=%s", key, lang_code, bot_id)
            return key

        if kwargs:
            try:
                val = val.format(**kwargs)
            except (KeyError, IndexError):
                logger.warning("Translation format error: key=%s kwargs=%s", key, kwargs)
        return val
