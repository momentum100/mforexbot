"""Password-gate FSM handlers (Screen 1c + combined gate's password half).

Flow (see docs/bot-flow.md → Screen 1c):

1. User taps "🔑 Ввести код доступа" on either the standalone or combined
   gate → callback `enter_password` fires, we edit the message to show the
   `password.required` prompt + `↩️ Отмена` cancel button, and we enter
   the FSM state `PasswordGate.waiting_for_password`.

2. Any plain text message received while in that state is compared, after
   `strip()`, as-is against `bot_config['access_password']`. Plaintext `==`
   per product decision; no hashing.

   - Match → `UPDATE users SET password_passed = 1`, clear FSM state, try
     to delete the user's password message (so it doesn't linger in chat
     history), then re-enter the gate via `_pass_registration_gate`
     (which will now pass) and show the main menu.
   - Mismatch → send `password.wrong` as a normal reply, remain in FSM
     state so the user can retry without re-issuing /start.

3. Cancel callback → clear FSM state and edit the prompt to
   `password.cancelled`.

Notes:
- Follows the same `build_router()` factory shape as start/language/menu
  (aiogram 3 Routers are stateful — one per Dispatcher, so the multi-bot
  launcher builds a fresh Router for each bot process slot).
- Routers must be included AFTER start/language in the dispatcher so the
  `enter_password` / `password_cancel` callbacks resolve here.
- FSM storage: aiogram 3's default Dispatcher storage is MemoryStorage,
  which is what we want — passwords are short-lived, per-process.
"""

import logging

from aiogram import Router, types
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

from db import Database
from i18n import TranslationService

logger = logging.getLogger(__name__)


class PasswordGate(StatesGroup):
    """Single-state FSM — we only need to know "user is entering password"."""
    waiting_for_password = State()


def _build_cancel_keyboard(i18n: TranslationService, lang: str) -> InlineKeyboardMarkup:
    """Inline keyboard with a single cancel button."""
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(
            text=i18n.t("password.btn_cancel", lang),
            callback_data="password_cancel",
        )],
    ])


def build_router() -> Router:
    """Build a fresh Router with password-gate FSM handlers bound.

    Must be called once per Dispatcher (see module docstring).
    """
    router = Router(name="password_gate")

    @router.callback_query(lambda c: c.data == "enter_password")
    async def cb_enter_password(
        callback: types.CallbackQuery,
        state: FSMContext,
        db: Database,
        i18n: TranslationService,
    ):
        """User tapped 'Enter access code' — show prompt and enter FSM state."""
        await callback.answer()
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"] if user else "en"

        text = i18n.t("password.required", lang)
        kb = _build_cancel_keyboard(i18n, lang)

        # Edit in place so the previous gate screen is replaced.
        try:
            # Photo message (banner) → edit caption; else edit text.
            if callback.message.photo:
                await callback.message.edit_caption(caption=text, reply_markup=kb)
            else:
                await callback.message.edit_text(text=text, reply_markup=kb)
        except Exception:
            # Fallback: send a fresh message if the original can't be edited.
            try:
                await callback.message.delete()
            except Exception:
                pass
            await callback.message.answer(text=text, reply_markup=kb)

        await state.set_state(PasswordGate.waiting_for_password)

    @router.callback_query(
        lambda c: c.data == "password_cancel",
        PasswordGate.waiting_for_password,
    )
    async def cb_password_cancel(
        callback: types.CallbackQuery,
        state: FSMContext,
        db: Database,
        i18n: TranslationService,
    ):
        """User cancelled password entry — clear state, show `password.cancelled`."""
        await callback.answer()
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"] if user else "en"

        await state.clear()

        text = i18n.t("password.cancelled", lang)
        try:
            if callback.message.photo:
                await callback.message.edit_caption(caption=text)
            else:
                await callback.message.edit_text(text=text)
        except Exception:
            await callback.message.answer(text=text)

    @router.message(PasswordGate.waiting_for_password)
    async def on_password_message(
        message: types.Message,
        state: FSMContext,
        db: Database,
        i18n: TranslationService,
        bot_config: dict,
    ):
        """Compare submitted text to bots.access_password (plaintext)."""
        user = db.get_user(message.from_user.id)
        lang = user["lang_code"] if user else "en"

        expected = (bot_config.get("access_password") or "").strip()
        submitted = (message.text or "").strip()

        # Defensive: if password was cleared in admin while user was at prompt,
        # treat as "gate disabled" — clear state and show main menu.
        if not expected:
            await state.clear()
            from handlers.start import _pass_registration_gate, _show_main_menu
            if not await _pass_registration_gate(
                message, db, i18n, bot_config, message.from_user.id, lang
            ):
                return
            user = db.get_user(message.from_user.id)
            await _show_main_menu(message, db, i18n, bot_config, user)
            return

        if submitted == expected:
            db.set_password_passed(message.from_user.id)
            db.set_user_registered(message.from_user.id)
            await state.clear()

            # Hygiene: delete the user's message so the code doesn't linger.
            try:
                await message.delete()
            except Exception:
                pass

            # Re-run the gate (now passing) and show main menu.
            from handlers.start import _pass_registration_gate, _show_main_menu
            if not await _pass_registration_gate(
                message, db, i18n, bot_config, message.from_user.id, lang
            ):
                return
            user = db.get_user(message.from_user.id)
            await _show_main_menu(message, db, i18n, bot_config, user)
            return

        # Mismatch — stay in state, let user retry.
        await message.answer(text=i18n.t("password.wrong", lang))

    return router
