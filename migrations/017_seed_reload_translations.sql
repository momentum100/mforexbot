-- Migration 017: Translations for the admin /reload command (success + forbidden).
-- Created: 2026-04-15

INSERT IGNORE INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'admin.reload_done', 'en', '✅ Translations reloaded: {count} rows.'),
(NULL, 'admin.reload_done', 'ru', '✅ Переводы перезагружены: {count} строк.'),
(NULL, 'admin.reload_forbidden', 'en', '⛔ Not authorized.'),
(NULL, 'admin.reload_forbidden', 'ru', '⛔ Нет доступа.');
