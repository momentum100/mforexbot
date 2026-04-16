-- Migration 019: Admin UI strings for the multi-bot launcher.
-- Keys: bot active/inactive status badges, restart button, restart confirm
-- dialog, and the post-restart flash message.
-- Created: 2026-04-15

INSERT IGNORE INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'admin.bots_status_active',    'en', '🟢 Active'),
(NULL, 'admin.bots_status_active',    'ru', '🟢 Активен'),
(NULL, 'admin.bots_status_inactive',  'en', '⚪ Inactive'),
(NULL, 'admin.bots_status_inactive',  'ru', '⚪ Отключен'),
(NULL, 'admin.bots_field_active',     'en', 'Active'),
(NULL, 'admin.bots_field_active',     'ru', 'Активен'),
(NULL, 'admin.bots_restart_btn',      'en', '🔄 Restart all bots'),
(NULL, 'admin.bots_restart_btn',      'ru', '🔄 Перезапустить все боты'),
(NULL, 'admin.bots_restart_confirm',  'en', 'Restart all bots now? Active bots will reload within ~10 seconds.'),
(NULL, 'admin.bots_restart_confirm',  'ru', 'Перезапустить все боты сейчас? Активные боты перезагрузятся в течение ~10 секунд.'),
(NULL, 'admin.bots_restart_flash',    'en', '✅ Restart requested — bots will reload within 10 seconds.'),
(NULL, 'admin.bots_restart_flash',    'ru', '✅ Перезапуск запрошен — боты перезагрузятся в течение 10 секунд.');
