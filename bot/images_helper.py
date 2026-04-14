"""Helper to pick image file for a given screen + language.

Rule: RU → ru version, any other lang → en version.
Images live in bot/images/{screen}_{lang}.jpg
"""
import os
from aiogram.types import FSInputFile

IMAGES_DIR = os.path.join(os.path.dirname(__file__), "images")


def get_image(screen: str, lang: str) -> FSInputFile | None:
    """Return FSInputFile for the screen image matching lang (ru or en fallback)."""
    variant = "ru" if lang == "ru" else "en"
    path = os.path.join(IMAGES_DIR, f"{screen}_{variant}.jpg")
    if os.path.exists(path):
        return FSInputFile(path)
    return None
