-- Migration 017: Global `settings` key-value table.
--
-- Scope: TRULY GLOBAL — no `bot_id` column. The platform shares a single
-- `postback_base_url` across all bots, used by the admin panel to render a
-- copy-paste-ready partner postback URL on each bot's edit form.
--
-- Table shape is intentionally generic so future global settings can land
-- here without another migration: INSERT new (`key`, `value`) rows as needed.
--
-- See:
--   - web/app/Controllers/AdminController::settings — GET/POST at /admin/settings
--   - web/app/Views/admin/settings.html             — the edit form
--   - web/app/Views/admin/bots.html                 — consumes postback_base_url
--
-- Created: 2026-04-15

CREATE TABLE IF NOT EXISTS settings (
    `key`      VARCHAR(64) NOT NULL PRIMARY KEY,
    `value`    TEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the postback base URL slot (empty by default — admin fills it in).
INSERT INTO settings (`key`, `value`) VALUES ('postback_base_url', NULL)
    ON DUPLICATE KEY UPDATE `key` = `key`;
