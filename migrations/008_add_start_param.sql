-- Migration 008: Add start_param to users
-- Created: 2026-04-14
-- Stores the Telegram deep-link start parameter (e.g. /start REF123) captured on first /start only.

ALTER TABLE users ADD COLUMN start_param VARCHAR(255) NULL AFTER status;
