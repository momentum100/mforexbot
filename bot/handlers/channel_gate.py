"""Shared "is the user subscribed to the linked channel?" helpers.

Before the refactor, the same chat_member probe + fallback UI was duplicated
between handlers/start.py (on /start) and handlers/language.py (after the
user picks a language). Both sites now call into these helpers instead.

`check_channel_subscription` reports the membership status in three-state
form (True / False / None-no-channel-configured); `show_channel_gate`
renders the "please subscribe" UI. Keeping the two apart lets the caller
decide whether to show the gate (cmd_start flow), skip it (no channel in
config), or combine it with further state (language picker re-checks the
gate after saving the lang).
"""

import logging

from aiogram import Bot, types
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

from db import Database
from i18n import TranslationService
from notifier import AdminNotifier

logger = logging.getLogger(__name__)


def _build_channel_keyboard(
    channel: str, i18n: TranslationService, lang: str,
) -> InlineKeyboardMarkup:
    """Two-button keyboard: "Subscribe" (URL) + "I subscribed, check again"."""
    channel_url = channel if channel.startswith("http") else f"https://t.me/{channel.lstrip('@')}"
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(
            text=i18n.t("channel.btn_subscribe", lang),
            callback_data="noop",
            url=channel_url,
        )],
        [InlineKeyboardButton(
            text=i18n.t("channel.btn_check", lang),
            callback_data="check_subscription",
        )],
    ])


async def check_channel_subscription(
    bot: Bot,
    db: Database,
    i18n: TranslationService,
    notifier: AdminNotifier,
    bot_config: dict,
    user_id: int,
    lang: str,
) -> bool | None:
    """Probe the linked channel's membership list for user_id.

    Returns:
        True  — user is a member / admin / creator.
        False — user is not a member, OR the probe failed (bot lacks admin
                rights in the channel). The caller should show the gate.
        None  — no linked_channel configured OR gate disabled via
                channel_gate_enabled → no gate.

    The notifier is used to raise a warning to admins when we can't probe
    the channel (the docs say "Block user per docs" in that case, hence we
    return False).
    """
    if not bot_config.get("channel_gate_enabled"):
        return None

    chat_id = bot_config.get("linked_channel_id") or bot_config.get("linked_channel")
    if not chat_id:
        return None

    try:
        member = await bot.get_chat_member(chat_id=chat_id, user_id=user_id)
        return member.status in ("member", "administrator", "creator")
    except Exception as e:
        # Bot likely lacks permissions
        logger.error("Channel check failed for %s: %s", chat_id, e)
        await notifier.notify(
            f"Bot lacks admin permissions in channel {chat_id}. Subscription check failed: {e}",
            level="warning",
        )
        return False  # Block user per docs


async def show_channel_gate(
    target: types.Message | types.CallbackQuery,
    i18n: TranslationService,
    channel: str,
    lang: str,
) -> None:
    """Render the "please subscribe" screen.

    For a CallbackQuery target we try edit_text first so the picker/menu
    message the user tapped is replaced in place; if the source message is a
    photo (can't be edited to text) or the edit otherwise fails, we fall
    back to answer(). For a Message target we simply answer().
    """
    kb = _build_channel_keyboard(channel, i18n, lang)
    text = i18n.t("channel.required", lang)

    if isinstance(target, types.CallbackQuery):
        try:
            await target.message.edit_text(text=text, reply_markup=kb)
            return
        except Exception:
            await target.message.answer(text=text, reply_markup=kb)
            return

    await target.answer(text=text, reply_markup=kb)
