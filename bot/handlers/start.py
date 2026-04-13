"""/start handler -- upsert user, auto-detect language, channel check, show menu."""

import logging

from aiogram import Router, types, Bot
from aiogram.filters import CommandStart
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

from db import Database
from i18n import TranslationService
from notifier import AdminNotifier

logger = logging.getLogger(__name__)
router = Router(name="start")


def _build_language_keyboard(languages: list[dict]) -> InlineKeyboardMarkup:
    """2-column grid of languages; last one centered if odd count."""
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


def _build_channel_keyboard(channel: str, i18n: TranslationService, lang: str) -> InlineKeyboardMarkup:
    channel_url = channel if channel.startswith("http") else f"https://t.me/{channel.lstrip('@')}"
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=i18n.t("channel.btn_subscribe", lang), callback_data="noop", url=channel_url)],
        [InlineKeyboardButton(text=i18n.t("channel.btn_check", lang), callback_data="check_subscription")],
    ])


async def _check_channel_subscription(
    bot: Bot, db: Database, i18n: TranslationService, notifier: AdminNotifier,
    bot_config: dict, user_id: int, lang: str,
) -> bool | None:
    """Return True if user is subscribed, False if not, None to skip (no channel configured)."""
    channel = bot_config.get("linked_channel")
    if not channel:
        return None

    try:
        member = await bot.get_chat_member(chat_id=channel, user_id=user_id)
        return member.status in ("member", "administrator", "creator")
    except Exception as e:
        # Bot likely lacks permissions
        logger.error("Channel check failed for %s: %s", channel, e)
        await notifier.notify(
            f"Bot lacks admin permissions in channel {channel}. Subscription check failed: {e}",
            level="warning",
        )
        return False  # Block user per docs


async def _show_main_menu(
    target: types.Message | types.CallbackQuery,
    db: Database,
    i18n: TranslationService,
    bot_config: dict,
    user: dict,
):
    """Send main menu (Screen 2)."""
    from handlers.menu import build_main_menu_keyboard

    lang = user["lang_code"]
    text = i18n.t("main_menu.title", lang)
    kb = build_main_menu_keyboard(i18n, lang, bot_config, user)

    if isinstance(target, types.CallbackQuery):
        await target.message.edit_text(text=text, reply_markup=kb)
    else:
        await target.answer(text=text, reply_markup=kb)


@router.message(CommandStart())
async def cmd_start(
    message: types.Message,
    bot: Bot,
    db: Database,
    i18n: TranslationService,
    notifier: AdminNotifier,
    bot_config: dict,
):
    tg_user = message.from_user
    # Detect language from Telegram client
    tg_lang = (tg_user.language_code or "")[:2].lower()
    supported = db.get_language_codes()

    # Check if user already exists (returning user)
    existing = db.get_user(tg_user.id)

    if existing:
        lang = existing["lang_code"]
    elif tg_lang in supported:
        lang = tg_lang
    else:
        lang = None  # will show language picker

    # Upsert user with detected or default lang
    user = db.upsert_user(
        telegram_id=tg_user.id,
        username=tg_user.username,
        first_name=tg_user.first_name,
        last_name=tg_user.last_name,
        lang_code=lang or "en",
    )

    # If language unknown -> show picker
    if lang is None:
        languages = db.get_languages()
        kb = _build_language_keyboard(languages)
        await message.answer(
            text=i18n.t("lang_select.title", "en"),
            reply_markup=kb,
        )
        return

    # Channel subscription check
    sub_result = await _check_channel_subscription(
        bot, db, i18n, notifier, bot_config, tg_user.id, lang,
    )
    if sub_result is False:
        # Show channel gate
        channel = bot_config["linked_channel"]
        kb = _build_channel_keyboard(channel, i18n, lang)
        await message.answer(
            text=i18n.t("channel.required", lang),
            reply_markup=kb,
        )
        return

    # Show main menu
    await _show_main_menu(message, db, i18n, bot_config, user)


@router.callback_query(lambda c: c.data == "check_subscription")
async def cb_check_subscription(
    callback: types.CallbackQuery,
    bot: Bot,
    db: Database,
    i18n: TranslationService,
    notifier: AdminNotifier,
    bot_config: dict,
):
    await callback.answer()
    user = db.get_user(callback.from_user.id)
    lang = user["lang_code"] if user else "en"

    sub_result = await _check_channel_subscription(
        bot, db, i18n, notifier, bot_config, callback.from_user.id, lang,
    )
    if sub_result is True:
        await _show_main_menu(callback, db, i18n, bot_config, user)
    else:
        await callback.answer(i18n.t("channel.not_subscribed", lang), show_alert=True)
