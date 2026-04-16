import os, mysql.connector
from dotenv import load_dotenv

load_dotenv("c:/Projects/m-bot-forex/.env")
c = mysql.connector.connect(
    host=os.getenv("DB_HOST"), port=int(os.getenv("DB_PORT")),
    user=os.getenv("DB_USER"), password=os.getenv("DB_PASS"),
    database=os.getenv("DB_NAME"),
)
cur = c.cursor()
rows = [
    (None, "register.required", "en", "To use the bot, please register on our partner platform:"),
    (None, "register.required", "ru", "Для использования бота, пожалуйста, зарегистрируйтесь на партнёрской платформе:"),
    (None, "register.btn_register", "en", "🔗 Register"),
    (None, "register.btn_register", "ru", "🔗 Зарегистрироваться"),
    (None, "register.btn_check", "en", "✅ I registered"),
    (None, "register.btn_check", "ru", "✅ Я зарегистрировался"),
    (None, "register.not_yet", "en", "⏳ We haven't received your registration yet. Please complete it and try again in a moment."),
    (None, "register.not_yet", "ru", "⏳ Регистрация ещё не подтверждена. Завершите её и попробуйте снова через минуту."),
    (None, "register.not_configured", "en", "⚠️ Registration is not configured for this bot. Please contact support."),
    (None, "register.not_configured", "ru", "⚠️ Регистрация для этого бота не настроена. Обратитесь в поддержку."),
]
for bot_id, key, lang, val in rows:
    cur.execute(
        "INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES (%s,%s,%s,%s) "
        "ON DUPLICATE KEY UPDATE value=VALUES(value)",
        (bot_id, key, lang, val),
    )
c.commit()
print("seeded", len(rows), "translations")
