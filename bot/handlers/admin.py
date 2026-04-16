"""Admin panel handlers (Screen 5): stats, broadcast, referral, promo stubs."""

import asyncio
import logging

from aiogram import Router, Bot, types, F
from aiogram.filters import Command
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

from db import Database
from i18n import TranslationService
from notifier import AdminNotifier

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# FSM states for broadcast flow
# ---------------------------------------------------------------------------

class BroadcastStates(StatesGroup):
    waiting_message = State()
    waiting_audience = State()
    waiting_confirm = State()


class ReferralStates(StatesGroup):
    waiting_url = State()


# ---------------------------------------------------------------------------
# Helpers (module-level — shared across Router instances)
# ---------------------------------------------------------------------------

def _is_admin(db: Database, telegram_id: int) -> bool:
    user = db.get_user(telegram_id)
    return bool(user and user.get("is_admin"))


def _admin_menu_kb(i18n: TranslationService, lang: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=i18n.t("admin.btn_broadcast", lang), callback_data="admin:broadcast")],
        [InlineKeyboardButton(text=i18n.t("admin.btn_stats", lang), callback_data="admin:stats")],
        [InlineKeyboardButton(text=i18n.t("admin.btn_referral", lang), callback_data="admin:referral")],
        [InlineKeyboardButton(text=i18n.t("admin.btn_promo", lang), callback_data="admin:promo")],
        [InlineKeyboardButton(text=i18n.t("admin.btn_back", lang), callback_data="menu:main")],
    ])


def _back_to_admin_kb(i18n: TranslationService, lang: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=i18n.t("admin.btn_back_admin", lang), callback_data="admin:menu")],
    ])


def _cancel_kb(i18n: TranslationService, lang: str, callback_data: str) -> InlineKeyboardMarkup:
    """Single Cancel button — used on FSM prompts to give the user an escape.

    Reuses the existing `admin.broadcast_btn_cancel` translation key
    (already seeded in migrations 005/007: "❌ Cancel" / "❌ Отмена") so
    we don't have to add a new translation migration just for this.
    """
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(
            text=i18n.t("admin.broadcast_btn_cancel", lang),
            callback_data=callback_data,
        )],
    ])


def build_router() -> Router:
    """Build a fresh Router with all admin handlers bound.

    Must be called once per Dispatcher (aiogram 3 Routers are stateful and
    cannot be attached to more than one Dispatcher).
    """
    router = Router(name="admin")

    # -----------------------------------------------------------------------
    # /reload command — manual translations cache refresh
    # -----------------------------------------------------------------------

    @router.message(Command("reload"))
    async def cmd_reload(
        message: types.Message,
        db: Database,
        i18n: TranslationService,
    ):
        """Admin-only: reload translations cache from DB without restart."""
        if not _is_admin(db, message.from_user.id):
            user = db.get_user(message.from_user.id)
            lang = user["lang_code"] if user else "en"
            await message.answer(i18n.t("admin.reload_forbidden", lang))
            return

        i18n.reload()
        count = len(i18n._cache)

        user = db.get_user(message.from_user.id)
        lang = user["lang_code"] if user else "en"
        logger.info(
            "Translations reloaded by telegram_id=%s username=%s — %d rows",
            message.from_user.id,
            message.from_user.username,
            count,
        )
        await message.answer(i18n.t("admin.reload_done", lang, count=count))

    # -----------------------------------------------------------------------
    # /admin command — always-available entry for admin users, bypasses gates
    # -----------------------------------------------------------------------

    @router.message(Command("admin"))
    async def cmd_admin(
        message: types.Message,
        db: Database,
        i18n: TranslationService,
        state: FSMContext,
    ):
        """Admin-only: open admin menu directly, bypassing all gates.

        Works at any point in the user flow (no language picked, not subscribed,
        not registered). Non-admins get a forbidden reply.
        """
        if not _is_admin(db, message.from_user.id):
            user = db.get_user(message.from_user.id)
            lang = user["lang_code"] if user else "en"
            await message.answer(i18n.t("admin.reload_forbidden", lang))
            return

        await state.clear()
        user = db.get_user(message.from_user.id)
        lang = user["lang_code"] if user and user.get("lang_code") else "en"
        text = i18n.t("admin.title", lang)
        kb = _admin_menu_kb(i18n, lang)
        await message.answer(text=text, reply_markup=kb)

    # -----------------------------------------------------------------------
    # Admin menu entry
    # -----------------------------------------------------------------------

    @router.callback_query(lambda c: c.data == "menu:admin")
    async def cb_admin_menu(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
        state: FSMContext,
    ):
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            return
        await state.clear()
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"]
        text = i18n.t("admin.title", lang)
        kb = _admin_menu_kb(i18n, lang)
        try:
            await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb)

    @router.callback_query(lambda c: c.data == "admin:menu")
    async def cb_admin_menu_back(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
        state: FSMContext,
    ):
        """Return to admin menu (from sub-screens)."""
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            return
        await state.clear()
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"]
        text = i18n.t("admin.title", lang)
        kb = _admin_menu_kb(i18n, lang)
        try:
            await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb)

    # -----------------------------------------------------------------------
    # Statistics (Screen 5a)
    # -----------------------------------------------------------------------

    @router.callback_query(lambda c: c.data == "admin:stats")
    async def cb_stats(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
    ):
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            return
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"]

        total = db.count_users_total()
        registered = db.count_users_registered()
        deposited = db.count_users_deposited()

        text = "\n".join([
            i18n.t("admin.stats_header", lang),
            "",
            i18n.t("admin.stats_total", lang, count=total),
            i18n.t("admin.stats_registered", lang, count=registered),
            i18n.t("admin.stats_deposited", lang, count=deposited),
        ])
        kb = _back_to_admin_kb(i18n, lang)
        try:
            await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb)

    # -----------------------------------------------------------------------
    # Broadcast (Screen 5b)
    # -----------------------------------------------------------------------

    @router.callback_query(lambda c: c.data == "admin:broadcast")
    async def cb_broadcast_start(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
        state: FSMContext,
    ):
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            return
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"]

        await state.set_state(BroadcastStates.waiting_message)
        text = i18n.t("admin.broadcast_prompt", lang)
        kb = _cancel_kb(i18n, lang, "broadcast:cancel_prompt")
        try:
            await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb)

    @router.callback_query(BroadcastStates.waiting_message, lambda c: c.data == "broadcast:cancel_prompt")
    async def cb_broadcast_cancel_prompt(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
        state: FSMContext,
    ):
        """Cancel from the broadcast-prompt state (before a message was sent).

        Clears FSM and returns to the admin menu so the user is not stuck
        waiting for a broadcast payload they no longer want to send.
        """
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            await state.clear()
            return
        await state.clear()
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"]
        text = i18n.t("admin.broadcast_cancelled", lang)
        kb = _back_to_admin_kb(i18n, lang)
        try:
            await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb)

    @router.message(BroadcastStates.waiting_message)
    async def on_broadcast_message(
        message: types.Message,
        db: Database,
        i18n: TranslationService,
        state: FSMContext,
    ):
        if not _is_admin(db, message.from_user.id):
            await state.clear()
            return
        user = db.get_user(message.from_user.id)
        lang = user["lang_code"]

        # Store source message for copy_message later
        await state.update_data(
            source_chat_id=message.chat.id,
            source_message_id=message.message_id,
        )
        await state.set_state(BroadcastStates.waiting_audience)

        kb = InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text=i18n.t("admin.broadcast_all", lang), callback_data="broadcast:all")],
            [InlineKeyboardButton(text=i18n.t("admin.broadcast_registered", lang), callback_data="broadcast:registered")],
            [InlineKeyboardButton(text=i18n.t("admin.broadcast_deposited", lang), callback_data="broadcast:deposited")],
            [InlineKeyboardButton(text=i18n.t("admin.broadcast_btn_cancel", lang), callback_data="broadcast:cancel")],
        ])
        await message.answer(text=i18n.t("admin.broadcast_audience", lang), reply_markup=kb)

    @router.callback_query(BroadcastStates.waiting_audience, lambda c: c.data and c.data.startswith("broadcast:"))
    async def cb_broadcast_audience(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
        state: FSMContext,
    ):
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            await state.clear()
            return

        choice = callback.data.split(":", 1)[1]
        if choice == "cancel":
            await state.clear()
            user = db.get_user(callback.from_user.id)
            lang = user["lang_code"]
            text = i18n.t("admin.broadcast_cancelled", lang)
            kb = _back_to_admin_kb(i18n, lang)
            try:
                await callback.message.edit_text(text=text, reply_markup=kb)
            except Exception:
                await callback.message.answer(text=text, reply_markup=kb)
            return

        # Map audience
        status_filter = None
        if choice == "registered":
            status_filter = "registered"
        elif choice == "deposited":
            status_filter = "deposited"

        users = db.get_users_by_status(status_filter)
        user_count = len(users)

        await state.update_data(audience_filter=status_filter, user_count=user_count)
        await state.set_state(BroadcastStates.waiting_confirm)

        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"]
        text = i18n.t("admin.broadcast_confirm", lang, count=user_count)
        kb = InlineKeyboardMarkup(inline_keyboard=[
            [
                InlineKeyboardButton(text=i18n.t("admin.broadcast_btn_send", lang), callback_data="broadcast:confirm"),
                InlineKeyboardButton(text=i18n.t("admin.broadcast_btn_cancel", lang), callback_data="broadcast:cancel_final"),
            ],
        ])
        try:
            await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb)

    @router.callback_query(BroadcastStates.waiting_confirm, lambda c: c.data in ("broadcast:confirm", "broadcast:cancel_final"))
    async def cb_broadcast_confirm(
        callback: types.CallbackQuery,
        bot: Bot,
        db: Database,
        i18n: TranslationService,
        notifier: AdminNotifier,
        state: FSMContext,
    ):
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            await state.clear()
            return

        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"]
        data = await state.get_data()
        await state.clear()

        if callback.data == "broadcast:cancel_final":
            text = i18n.t("admin.broadcast_cancelled", lang)
            kb = _back_to_admin_kb(i18n, lang)
            try:
                await callback.message.edit_text(text=text, reply_markup=kb)
            except Exception:
                await callback.message.answer(text=text, reply_markup=kb)
            return

        # Execute broadcast
        source_chat_id = data["source_chat_id"]
        source_message_id = data["source_message_id"]
        audience_filter = data.get("audience_filter")

        recipients = db.get_users_by_status(audience_filter)
        total = len(recipients)
        success = 0

        for rec in recipients:
            try:
                await bot.copy_message(
                    chat_id=rec["telegram_id"],
                    from_chat_id=source_chat_id,
                    message_id=source_message_id,
                )
                success += 1
            except Exception:
                logger.debug("Broadcast failed for user %s", rec["telegram_id"], exc_info=True)
            # Respect rate limits: ~30 msgs/sec
            if success % 25 == 0:
                await asyncio.sleep(1)

        text = i18n.t("admin.broadcast_done", lang, success=success, total=total)
        kb = _back_to_admin_kb(i18n, lang)
        try:
            await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb)

        await notifier.notify(
            f"Broadcast completed: {success}/{total}",
            level="info",
        )

    # -----------------------------------------------------------------------
    # Referral link (Screen 5c)
    # -----------------------------------------------------------------------

    @router.callback_query(lambda c: c.data == "admin:referral")
    async def cb_referral(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
        bot_config: dict,
    ):
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            return
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"]

        template = bot_config.get("referral_url_template") or "—"
        example = (
            template.replace("{user_id}", str(callback.from_user.id))
                    .replace("{bot_id}", str(bot_config["id"]))
            if template != "—" else "—"
        )

        text = (
            f"{i18n.t('admin.btn_referral', lang)}\n\n"
            f"Current template:\n<code>{template}</code>\n\n"
            f"Example (your ID):\n{example}\n\n"
            f"Placeholders:\n"
            f"<code>{{user_id}}</code> — Telegram ID (→ partner click_id)\n"
            f"<code>{{bot_id}}</code> — bot ID (→ partner sub_id1)"
        )
        kb = InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text="✏️ Edit", callback_data="admin:referral_edit")],
            [InlineKeyboardButton(text=i18n.t("admin.btn_back_admin", lang), callback_data="admin:menu")],
        ])
        try:
            await callback.message.edit_text(text=text, reply_markup=kb, parse_mode="HTML")
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb, parse_mode="HTML")

    @router.callback_query(lambda c: c.data == "admin:referral_edit")
    async def cb_referral_edit(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
        state: FSMContext,
    ):
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            return
        await state.set_state(ReferralStates.waiting_url)
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"] if user else "en"
        text = (
            "Send the new referral URL template.\n"
            "Placeholders:\n"
            "• <code>{user_id}</code> — Telegram user ID (→ partner click_id)\n"
            "• <code>{bot_id}</code> — bot ID (→ partner sub_id1)\n\n"
            "Example:\n"
            "<code>https://example.com/smart/XXX?click_id={user_id}&amp;sub_id1={bot_id}</code>"
        )
        kb = _cancel_kb(i18n, lang, "referral:cancel")
        try:
            await callback.message.edit_text(text=text, reply_markup=kb, parse_mode="HTML")
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb, parse_mode="HTML")

    @router.callback_query(ReferralStates.waiting_url, lambda c: c.data == "referral:cancel")
    async def cb_referral_cancel(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
        state: FSMContext,
    ):
        """Cancel referral URL edit — clear FSM, return to admin menu."""
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            await state.clear()
            return
        await state.clear()
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"]
        text = i18n.t("admin.title", lang)
        kb = _admin_menu_kb(i18n, lang)
        try:
            await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb)

    @router.message(ReferralStates.waiting_url)
    async def on_referral_url(
        message: types.Message,
        db: Database,
        i18n: TranslationService,
        state: FSMContext,
        bot_config: dict,
    ):
        if not _is_admin(db, message.from_user.id):
            await state.clear()
            return
        user = db.get_user(message.from_user.id)
        lang = user["lang_code"]

        new_url = (message.text or "").strip()
        await state.clear()

        if not new_url:
            await message.answer("❌ Empty URL, cancelled.", reply_markup=_back_to_admin_kb(i18n, lang))
            return

        db.update_referral_url(new_url)
        bot_config["referral_url_template"] = new_url  # update in-memory config

        example = (
            new_url.replace("{user_id}", str(message.from_user.id))
                   .replace("{bot_id}", str(bot_config["id"]))
        )
        text = (
            f"✅ Referral URL updated.\n\n"
            f"New template:\n<code>{new_url}</code>\n\n"
            f"Example (your ID):\n{example}"
        )
        await message.answer(text=text, reply_markup=_back_to_admin_kb(i18n, lang), parse_mode="HTML")

    # -----------------------------------------------------------------------
    # Promo codes (stub)
    # -----------------------------------------------------------------------

    @router.callback_query(lambda c: c.data == "admin:promo")
    async def cb_promo(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
    ):
        await callback.answer()
        if not _is_admin(db, callback.from_user.id):
            return
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"]

        text = i18n.t("admin.btn_promo", lang) + "\n\n(coming soon)"
        kb = _back_to_admin_kb(i18n, lang)
        try:
            await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            await callback.message.answer(text=text, reply_markup=kb)

    return router
