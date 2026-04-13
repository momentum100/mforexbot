"""Main menu (Screen 2) handler and keyboard builder."""

from aiogram import Router, types
from aiogram.filters import Command
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton, WebAppInfo

from db import Database
from i18n import TranslationService

router = Router(name="menu")


def build_main_menu_keyboard(
    i18n: TranslationService,
    lang: str,
    bot_config: dict,
    user: dict,
) -> InlineKeyboardMarkup:
    """Build main menu inline keyboard (Screen 2)."""
    rows: list[list[InlineKeyboardButton]] = []

    # Row 1: Instruction + Change language (2 columns)
    rows.append([
        InlineKeyboardButton(
            text=i18n.t("main_menu.btn_instruction", lang),
            callback_data="menu:instruction",
        ),
        InlineKeyboardButton(
            text=i18n.t("main_menu.btn_language", lang),
            callback_data="menu:language",
        ),
    ])

    # Row 2: Support (URL button, centered)
    support_link = bot_config.get("support_link")
    if support_link:
        rows.append([
            InlineKeyboardButton(
                text=i18n.t("main_menu.btn_support", lang),
                url=support_link,
            ),
        ])

    # Row 3: Get signal (web_app button)
    webapp_url = bot_config.get("webapp_url")
    if webapp_url:
        rows.append([
            InlineKeyboardButton(
                text=i18n.t("main_menu.btn_signal", lang),
                web_app=WebAppInfo(url=webapp_url),
            ),
        ])

    # Admin button (visible only for admins)
    if user.get("is_admin"):
        rows.append([
            InlineKeyboardButton(
                text=i18n.t("main_menu.btn_admin", lang),
                callback_data="menu:admin",
            ),
        ])

    return InlineKeyboardMarkup(inline_keyboard=rows)


@router.callback_query(lambda c: c.data == "menu:main")
async def cb_main_menu(
    callback: types.CallbackQuery,
    db: Database,
    i18n: TranslationService,
    bot_config: dict,
):
    """Return to main menu from any sub-screen."""
    await callback.answer()
    user = db.get_user(callback.from_user.id)
    if not user:
        return
    lang = user["lang_code"]
    text = i18n.t("main_menu.title", lang)
    kb = build_main_menu_keyboard(i18n, lang, bot_config, user)
    try:
        await callback.message.edit_text(text=text, reply_markup=kb)
    except Exception:
        await callback.message.answer(text=text, reply_markup=kb)


@router.message(Command("support"))
async def cmd_support(
    message: types.Message,
    db: Database,
    i18n: TranslationService,
    bot_config: dict,
):
    """Handle /support command -- send support link."""
    user = db.get_user(message.from_user.id)
    lang = user["lang_code"] if user else "en"
    support_link = bot_config.get("support_link")
    if support_link:
        kb = InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text=i18n.t("main_menu.btn_support", lang), url=support_link)],
        ])
        await message.answer(text=i18n.t("main_menu.btn_support", lang), reply_markup=kb)
    else:
        await message.answer(text=i18n.t("main_menu.btn_support", lang))


@router.message(Command("signal"))
async def cmd_signal(
    message: types.Message,
    db: Database,
    i18n: TranslationService,
    bot_config: dict,
):
    """Handle /signal command -- open web app."""
    user = db.get_user(message.from_user.id)
    lang = user["lang_code"] if user else "en"
    webapp_url = bot_config.get("webapp_url")
    if webapp_url:
        kb = InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(
                text=i18n.t("main_menu.btn_signal", lang),
                web_app=WebAppInfo(url=webapp_url),
            )],
        ])
        await message.answer(text=i18n.t("main_menu.btn_signal", lang), reply_markup=kb)
    else:
        await message.answer(text=i18n.t("main_menu.btn_signal", lang))
