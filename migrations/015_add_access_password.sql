-- Migration 015: Password gate (alternative to affiliate postback gate).
-- Adds:
--   - bots.access_password     — plaintext bot-wide shared access code. If set, enables Screen 1c (password gate)
--                                and takes precedence over the affiliate postback gate (Screen 1b).
--   - users.password_passed    — per-user flag, set to 1 once the user has successfully entered the password.
-- See docs/bot-flow.md → "Screen 1c: Password Gate" for UX, resolution logic, and translation keys.
-- Created: 2026-04-15

ALTER TABLE bots
    ADD COLUMN access_password VARCHAR(64) NULL
    COMMENT 'If set, enables password gate (Screen 1c) instead of affiliate postback gate. Plaintext, shared bot-wide.'
    AFTER postback_secret;

ALTER TABLE users
    ADD COLUMN password_passed TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 once user has entered bots.access_password correctly. Ignored when bots.access_password IS NULL.'
    AFTER status;
