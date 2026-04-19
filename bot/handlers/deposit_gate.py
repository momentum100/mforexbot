"""Deposit-gate helpers (Screen 1d — post-registration FTD gate).

Screen 1d in docs/bot-flow.md: after the user has passed the channel +
registration gates but has not yet made their first real-money deposit,
we block access to the main menu and show three buttons:

  - "Сделать депозит" — URL button that routes back through the partner
    cabinet via the same `referral_url_template` used by the reg gate
    (so postbacks can attribute the FTD to this user).
  - "Я сделал депозит" — callback `check_deposit` re-checks
    `postback_events` for an `ftd` row; on success we show the main menu,
    otherwise we pop the `deposit.not_yet` alert and leave the gate UI
    untouched so the user can retry.
  - "Поддержка" — URL to `bots.support_link`. Row is omitted entirely
    when support_link is NULL/empty (no dead-end button with no target).

Enablement is the usual toggle-plus-config pair:
  deposit_gate_enabled = 1 AND referral_url_template non-empty.
If `deposit_gate_enabled` is on but `referral_url_template` is empty, the
gate is a silent no-op (nothing to render — skip).

No FSM here — this is a plain "render UI, wait for callback" flow modeled
after start.py's registration-gate helpers. Follows the aiogram router
factory rule (memory: project_aiogram_router_factory) — build_router() is
called once per Dispatcher by the launcher.
"""

import logging

from aiogram import Router, types
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

from constants import PostbackEvent
from db import Database
from i18n import TranslationService

logger = logging.getLogger(__name__)


def _build_deposit_url(bot_config: dict, telegram_id: int) -> str | None:
    """Substitute {user_id} -> telegram_id, {bot_id} -> bot_id into the stored template."""
    template = bot_config.get("referral_url_template")
    if not template:
        return None
    return (
        template
        .replace("{user_id}", str(telegram_id))
        .replace("{bot_id}", str(bot_config["id"]))
    )


def _build_deposit_keyboard(
    ref_url: str,
    support_link: str | None,
    i18n: TranslationService,
    lang: str,
) -> InlineKeyboardMarkup:
    """3-row keyboard: deposit URL + check callback + (optional) support URL."""
    rows: list[list[InlineKeyboardButton]] = [
        [InlineKeyboardButton(text=i18n.t("deposit.btn_deposit", lang), callback_data="noop", url=ref_url)],
        [InlineKeyboardButton(text=i18n.t("deposit.btn_check", lang), callback_data="check_deposit")],
    ]
    if support_link:
        rows.append(
            [InlineKeyboardButton(text=i18n.t("deposit.btn_support", lang), callback_data="noop", url=support_link)]
        )
    return InlineKeyboardMarkup(inline_keyboard=rows)


async def _pass_deposit_gate(
    target: types.Message | types.CallbackQuery,
    db: Database,
    i18n: TranslationService,
    bot_config: dict,
    telegram_id: int,
    lang: str,
) -> bool:
    """Apply the deposit gate (Screen 1d).

    Returns True if the gate is disabled or already passed (FTD postback on
    record). Otherwise sends the deposit gate UI and returns False.

    Resolution:

      deposit_on = bool(bot_config.deposit_gate_enabled)
      ref_url   = rendered referral_url_template (None if template empty)

      - not deposit_on OR ref_url is None → pass (silent no-op)
      - has_postback_event(telegram_id, 'ftd') → pass
      - otherwise → render gate UI, return False
    """
    deposit_on = bool(bot_config.get("deposit_gate_enabled"))
    ref_url = _build_deposit_url(bot_config, telegram_id)

    # Gate effectively disabled — either the toggle is off or there's no
    # URL template to route through, so nothing meaningful to render.
    if not deposit_on or ref_url is None:
        return True

    if db.has_postback_event(telegram_id, PostbackEvent.FTD):
        return True

    support_link = (bot_config.get("support_link") or "").strip() or None
    text = i18n.t("deposit.required", lang)
    kb = _build_deposit_keyboard(ref_url, support_link, i18n, lang)

    msg = target.message if isinstance(target, types.CallbackQuery) else target

    if isinstance(target, types.CallbackQuery):
        try:
            await target.message.delete()
        except Exception:
            pass
    await msg.answer(text=text, reply_markup=kb)
    return False


def build_router() -> Router:
    """Build a fresh Router with deposit-gate handlers bound.

    Must be called once per Dispatcher (see module docstring).
    """
    router = Router(name="deposit_gate")

    @router.callback_query(lambda c: c.data == "check_deposit")
    async def cb_check_deposit(
        callback: types.CallbackQuery,
        db: Database,
        i18n: TranslationService,
        bot_config: dict,
    ):
        user = db.get_user(callback.from_user.id)
        lang = user["lang_code"] if user else "en"

        if db.has_postback_event(callback.from_user.id, PostbackEvent.FTD):
            await callback.answer()
            # Inline import to avoid circular dependency at module load
            # (mirrors language.py pattern for _show_main_menu).
            from handlers.start import _show_main_menu
            await _show_main_menu(callback, db, i18n, bot_config, user)
        else:
            await callback.answer(i18n.t("deposit.not_yet", lang), show_alert=True)

    return router
