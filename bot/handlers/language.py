"""Language selection handler (Screen 1 / change language)."""

import logging

from aiogram import Router, types, Bot
from aiogram.filters import Command
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

from db import Database
from i18n import TranslationService
from notifier import AdminNotifier
from handlers.channel_gate import check_channel_subscription, show_channel_gate

logger = logging.getLogger(__name__)


def _build_language_keyboard(languages: list[dict]) -> InlineKeyboardMarkup:
    """2-column grid; last row centered if odd."""
    buttons: list[list[InlineKeyboardButton]] = []
    row: list[InlineKeyboardButton] = []
    for lang in languages:
        label = f"{lang['flag']} {lang['native_name']}"
        btn = InlineKeyboardButton(text=label, callback_data=f"lang:{lang['code']}")
        row.append(btn)
        if len(row) == 2:
            buttons.append(row)
            row = []
    if row:
        buttons.append(row)
    return InlineKeyboardMarkup(inline_keyboard=buttons)


def build_router() -> Router:
    """Build a fresh Router with language handlers bound.

    Must be called once per Dispatcher.
    """
    router = Router(name="language")

    @router.callback_query(lambda c: c.data == "menu:language")
    async def cb_show_language_picker(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
    ):
        """Show language selection keyboard from main menu."""
        await callback.answer()
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"] if user else "en"
        languages = db.get_languages()
        kb = _build_language_keyboard(languages)
        text = i18n.t("lang_select.title", lang)
        try:
            await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb)

    @router.message(Command("language"))
    async def cmd_language(
        message: types.Message,
        db: Database,
        i18n: TranslationService,
    ):
        """Handle /language command."""
        user = db.get_user(message.from_user.id)
        lang = user["lang_code"] if user else "en"
        languages = db.get_languages()
        kb = _build_language_keyboard(languages)
        await message.answer(text=i18n.t("lang_select.title", lang), reply_markup=kb)

    @router.callback_query(lambda c: c.data and c.data.startswith("lang:"))
    async def cb_select_language(
        callback: types.CallbackQuery,
        bot: Bot,
        db: Database,
        i18n: TranslationService,
        notifier: AdminNotifier,
        bot_config: dict,
    ):
        """User picked a language -> update DB, show main menu (with channel check)."""
        await callback.answer()
        chosen_code = callback.data.split(":", 1)[1]
        telegram_id = callback.from_user.id

        db.update_user_lang(telegram_id, chosen_code)
        user = db.get_user(telegram_id)
        if not user:
            return

        lang = user["lang_code"]

        # Channel subscription check (shared with /start flow).
        sub_result = await check_channel_subscription(
            bot, db, i18n, notifier, bot_config, telegram_id, lang,
        )
        if sub_result is False:
            # Replace the language picker with the channel gate in place
            # (show_channel_gate tries edit_text first for CallbackQuery).
            await show_channel_gate(callback, i18n, bot_config.get("linked_channel") or "", lang)
            return

        # Registration gate
        from handlers.start import _pass_registration_gate, _show_main_menu
        if not await _pass_registration_gate(callback, db, i18n, bot_config, telegram_id, lang):
            return

        # Deposit gate (Screen 1d)
        from handlers.deposit_gate import _pass_deposit_gate
        if not await _pass_deposit_gate(callback, db, i18n, bot_config, telegram_id, lang):
            return

        # Show main menu (with banner image)
        await _show_main_menu(callback, db, i18n, bot_config, user)

    return router
