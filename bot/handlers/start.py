"""/start handler -- upsert user, auto-detect language, channel check, show menu."""

import logging

from aiogram import Router, types, Bot
from aiogram.filters import CommandStart, CommandObject
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

from constants import PostbackEvent
from db import Database
from i18n import TranslationService
from notifier import AdminNotifier
from handlers.channel_gate import check_channel_subscription, show_channel_gate

logger = logging.getLogger(__name__)


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


def _build_referral_url(bot_config: dict, telegram_id: int) -> str | None:
    """Substitute {user_id} -> telegram_id, {bot_id} -> bot_id into the stored template."""
    template = bot_config.get("referral_url_template")
    if not template:
        return None
    return (
        template
        .replace("{user_id}", str(telegram_id))
        .replace("{bot_id}", str(bot_config["id"]))
    )


def _build_register_keyboard(ref_url: str, i18n: TranslationService, lang: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=i18n.t("register.btn_register", lang), callback_data="noop", url=ref_url)],
        [InlineKeyboardButton(text=i18n.t("register.btn_check", lang), callback_data="check_registration")],
    ])


def _build_combined_gate_keyboard(
    ref_url: str, i18n: TranslationService, lang: str,
) -> InlineKeyboardMarkup:
    """Combined gate: affiliate register + "enter access code" button.

    Used when bot has BOTH `referral_url_template` and `access_password`.
    Tapping the bottom button enters the password FSM (see
    handlers/password_gate.py).
    """
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=i18n.t("register.btn_register", lang), callback_data="noop", url=ref_url)],
        [InlineKeyboardButton(text=i18n.t("register.btn_check", lang), callback_data="check_registration")],
        [InlineKeyboardButton(text=i18n.t("register.btn_enter_password", lang), callback_data="enter_password")],
    ])


def _build_password_only_keyboard(
    i18n: TranslationService, lang: str,
) -> InlineKeyboardMarkup:
    """Standalone password gate (Screen 1c) initial keyboard.

    Single button — tapping it enters the password FSM. We use the same
    `enter_password` callback as the combined gate so password_gate.py only
    has to know one entry point.
    """
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=i18n.t("register.btn_enter_password", lang), callback_data="enter_password")],
    ])


async def _show_main_menu(
    target: types.Message | types.CallbackQuery,
    db: Database,
    i18n: TranslationService,
    bot_config: dict,
    user: dict,
):
    """Send main menu (Screen 2) with banner image."""
    from handlers.menu import build_main_menu_keyboard
    from images_helper import get_image

    # Mark user as registered (idempotent, only upgrades 'new' → 'registered')
    db.set_user_registered(user["telegram_id"])

    lang = user["lang_code"]
    text = i18n.t("main_menu.title", lang)
    kb = build_main_menu_keyboard(i18n, lang, bot_config, user)
    image = get_image("main_menu", lang)

    msg = target.message if isinstance(target, types.CallbackQuery) else target

    # Delete previous message if coming from callback (can't edit text->photo)
    if isinstance(target, types.CallbackQuery):
        try:
            await target.message.delete()
        except Exception:
            pass

    if image:
        await msg.answer_photo(photo=image, caption=text, reply_markup=kb)
    else:
        await msg.answer(text=text, reply_markup=kb)


async def _pass_registration_gate(
    target: types.Message | types.CallbackQuery,
    db: Database,
    i18n: TranslationService,
    bot_config: dict,
    telegram_id: int,
    lang: str,
) -> bool:
    """Apply the post-channel gate (Screen 1b / 1c / combined).

    Returns True if the user has already passed the gate (or no gate is
    configured). Otherwise sends the appropriate gate UI and returns False.

    Resolution (matches docs/bot-flow.md → "Gate selection logic"):

      has_password  = bool(bot_config.access_password)         AND bool(bot_config.password_gate_enabled)
      has_affiliate = bool(bot_config.referral_url_template)   AND bool(bot_config.reg_gate_enabled)
      passed = users.password_passed OR has_reg_postback(user)

      Each "has X" requires BOTH the companion config field (non-empty)
      AND its enablement toggle on the `bots` row. Toggling on without
      filling the config is a silent no-op — nothing to render a gate for.

      - neither set       → no gate, pass
      - already passed    → pass
      - both set          → combined gate (Screen 1b layout + password button)
      - affiliate only    → Screen 1b (affiliate-only postback gate)
      - password only     → Screen 1c (standalone password gate)
    """
    access_password = (bot_config.get("access_password") or "").strip()
    has_password = bool(access_password) and bool(bot_config.get("password_gate_enabled"))
    ref_url = _build_referral_url(bot_config, telegram_id)
    has_affiliate = bool(ref_url) and bool(bot_config.get("reg_gate_enabled"))

    # No gate configured at all.
    if not has_password and not has_affiliate:
        return True

    # Already passed by either path.
    user = db.get_user(telegram_id)
    if user and user.get("password_passed"):
        return True
    user_status = (user or {}).get("status")
    # Status reaching REGISTERED or DEPOSITED implies the reg gate is satisfied —
    # either via affiliate postback (which sets DEPOSITED on FTD too) or via
    # password entry (which sets whichever status `password_grants_status` dictates).
    if has_affiliate and (
        db.has_postback_event(telegram_id, PostbackEvent.REG)
        or user_status in {"registered", "deposited"}
    ):
        return True

    # Show the correct gate shape.
    msg = target.message if isinstance(target, types.CallbackQuery) else target

    if has_affiliate and has_password:
        text = i18n.t("register.combined_required", lang)
        kb = _build_combined_gate_keyboard(ref_url, i18n, lang)
    elif has_affiliate:
        text = i18n.t("register.required", lang)
        kb = _build_register_keyboard(ref_url, i18n, lang)
    else:
        # Password-only (standalone Screen 1c).
        text = i18n.t("password.required", lang)
        kb = _build_password_only_keyboard(i18n, lang)

    if isinstance(target, types.CallbackQuery):
        try:
            await target.message.delete()
        except Exception:
            pass
    await msg.answer(text=text, reply_markup=kb)
    return False


def build_router() -> Router:
    """Build a fresh Router with all start-flow handlers bound to it.

    aiogram 3 Router instances are stateful (they track parent Dispatcher)
    and cannot be reused across dispatchers, so each bot/Dispatcher needs
    its own fresh Router. Call this once per Dispatcher.
    """
    router = Router(name="start")

    @router.message(CommandStart())
    async def cmd_start(
        message: types.Message,
        command: CommandObject,
        bot: Bot,
        db: Database,
        i18n: TranslationService,
        notifier: AdminNotifier,
        bot_config: dict,
    ):
        tg_user = message.from_user
        start_param = (command.args or "").strip()

        # Check if user already exists (returning user)
        existing = db.get_user(tg_user.id)

        if existing:
            lang = existing["lang_code"]
        else:
            lang = None  # new user -> always show language picker

        # Upsert user with detected or default lang
        user = db.upsert_user(
            telegram_id=tg_user.id,
            username=tg_user.username,
            first_name=tg_user.first_name,
            last_name=tg_user.last_name,
            lang_code=lang or "en",
        )

        # Capture start_param on first /start only (idempotent)
        if start_param:
            db.set_start_param_if_empty(tg_user.id, start_param)

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
        sub_result = await check_channel_subscription(
            bot, db, i18n, notifier, bot_config, tg_user.id, lang,
        )
        if sub_result is False:
            # Show channel gate
            await show_channel_gate(message, i18n, bot_config["linked_channel"], lang)
            return

        # Registration gate
        if not await _pass_registration_gate(message, db, i18n, bot_config, tg_user.id, lang):
            return

        # Deposit gate (Screen 1d)
        from handlers.deposit_gate import _pass_deposit_gate
        if not await _pass_deposit_gate(message, db, i18n, bot_config, tg_user.id, lang):
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
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"] if user else "en"

        sub_result = await check_channel_subscription(
            bot, db, i18n, notifier, bot_config, callback.from_user.id, lang,
        )
        if sub_result is True:
            await callback.answer()
            if not await _pass_registration_gate(callback, db, i18n, bot_config, callback.from_user.id, lang):
                return
            from handlers.deposit_gate import _pass_deposit_gate
            if not await _pass_deposit_gate(callback, db, i18n, bot_config, callback.from_user.id, lang):
                return
            await _show_main_menu(callback, db, i18n, bot_config, user)
        else:
            await callback.answer(i18n.t("channel.not_subscribed", lang), show_alert=True)

    @router.callback_query(lambda c: c.data == "check_registration")
    async def cb_check_registration(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
        bot_config: dict,
    ):
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"] if user else "en"
        user_status = (user or {}).get("status")

        if (
            db.has_postback_event(callback.from_user.id, PostbackEvent.REG)
            or user_status in {"registered", "deposited"}
        ):
            await callback.answer()
            from handlers.deposit_gate import _pass_deposit_gate
            if not await _pass_deposit_gate(callback, db, i18n, bot_config, callback.from_user.id, lang):
                return
            await _show_main_menu(callback, db, i18n, bot_config, user)
        else:
            await callback.answer(i18n.t("register.not_yet", lang), show_alert=True)

    return router
