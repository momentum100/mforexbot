# AdminNotifier

Dedicated class for sending notifications to all bot admins (`is_admin = 1`). Used whenever the system needs to alert admins about issues, events, or errors.

## Class: `AdminNotifier`

### Constructor
- Takes `bot` instance and `db` connection
- Loads admin user list: `SELECT telegram_id FROM users WHERE bot_id = ? AND is_admin = 1`

### Method: `notify(message: str, level: str = 'info')`
- Sends message to all admins for the current bot
- Levels: `info`, `warning`, `error`
- Prefixes:
  - `info` → `ℹ️`
  - `warning` → `⚠️`
  - `error` → `🚨`
- Silently skips if sending fails (don't crash the bot over a notification)

### Use Cases

| Event | Level | Message |
|-------|-------|---------|
| Bot lacks channel admin permissions | warning | `⚠️ Бот не имеет прав администратора в канале {channel}. Проверка подписки пропущена.` |
| Broadcast completed | info | `ℹ️ Рассылка завершена: {success}/{total}` |
| Broadcast failed | error | `🚨 Ошибка рассылки: {error}` |
| Bot startup | info | `ℹ️ Бот запущен (bot_id={id})` |
| Bot shutdown | info | `ℹ️ Бот остановлен` |
| Database connection error | error | `🚨 Ошибка подключения к БД: {error}` |
| Unknown error / exception | error | `🚨 Необработанная ошибка: {error}` |

### Design
- Standalone class, injected where needed
- Does NOT raise exceptions — all sends are try/except wrapped
- Can be extended with new notification types without modifying existing code (Open/Closed)
