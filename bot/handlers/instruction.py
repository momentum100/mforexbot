"""Instruction screen (Screen 3) handler."""

from aiogram import Router, types
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

from db import Database
from i18n import TranslationService
from images_helper import get_image


def build_router() -> Router:
    """Build a fresh Router with instruction handlers bound.

    Must be called once per Dispatcher.
    """
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

        image = get_image("instructions", lang)

        # Delete previous message (can't edit text->photo)
        try:
            await callback.message.delete()
        except Exception:
            pass

        if image:
            await callback.message.answer_photo(photo=image, caption=text, reply_markup=kb)
        else:
            await callback.message.answer(text=text, reply_markup=kb)

    return router
