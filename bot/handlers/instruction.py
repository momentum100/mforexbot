"""Instruction screen (Screen 3) handler."""

from aiogram import Router, types
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

from db import Database
from i18n import TranslationService

router = Router(name="instruction")


@router.callback_query(lambda c: c.data == "menu:instruction")
async def cb_instruction(
    callback: types.CallbackQuery,
    db: Database,
    i18n: TranslationService,
):
    await callback.answer()
    user = db.get_user(callback.from_user.id)
    if not user:
        return
    lang = user["lang_code"]

    # Build instruction text from translation keys
    parts = [
        i18n.t("instruction.intro", lang),
        "",
        i18n.t("instruction.training", lang),
        "",
        i18n.t("instruction.accuracy", lang),
        "",
        i18n.t("instruction.step1", lang),
        i18n.t("instruction.step2", lang),
        i18n.t("instruction.step3", lang),
        i18n.t("instruction.step4", lang),
        i18n.t("instruction.step5", lang),
        i18n.t("instruction.warning", lang),
        "",
        i18n.t("instruction.no_access", lang),
    ]
    text = "\n".join(parts)

    kb = InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(
            text=i18n.t("instruction.btn_back", lang),
            callback_data="menu:main",
        )],
    ])

    try:
        await callback.message.edit_text(text=text, reply_markup=kb)
    except Exception:
        await callback.message.answer(text=text, reply_markup=kb)
