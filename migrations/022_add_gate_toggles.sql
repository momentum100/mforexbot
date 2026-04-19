-- Migration 022: Per-bot toggles for the four access gates + add deposit gate (Screen 1d).
-- Converts each gate from "implicitly enabled when its source field is set" to an explicit
-- admin-controlled TINYINT(1) flag, and introduces a brand-new deposit gate that reuses the
-- existing referral_url_template as the deposit URL.
-- Adds:
--   - bots.channel_gate_enabled  — toggle for Screen 1a (channel subscription gate).
--   - bots.reg_gate_enabled      — toggle for Screen 1b (affiliate registration gate).
--   - bots.deposit_gate_enabled  — toggle for Screen 1d (deposit gate, new).
--   - bots.password_gate_enabled — toggle for Screen 1c (password gate).
-- Backfill: existing bots with data in the gate's source field keep their gate ON (no regression);
-- deposit_gate_enabled stays 0 because this is a brand-new feature and admins must opt in.
-- See docs/bot-flow.md → "Screen 1d" and "Admin: Bot Edit Form" for UX, field ordering,
-- and "Translation keys for the deposit gate" for the seeds in migration 023.
-- Created: 2026-04-19

ALTER TABLE bots
    ADD COLUMN channel_gate_enabled TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'If 1 and linked_channel_id set, enable Screen 1a (channel subscription gate).'
    AFTER linked_channel_id;

ALTER TABLE bots
    ADD COLUMN reg_gate_enabled TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'If 1 and referral_url_template set, enable Screen 1b (affiliate registration gate).'
    AFTER referral_url_template;

ALTER TABLE bots
    ADD COLUMN deposit_gate_enabled TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'If 1 and referral_url_template set, enable Screen 1d (deposit gate). Uses referral_url_template as deposit URL.'
    AFTER reg_gate_enabled;

ALTER TABLE bots
    ADD COLUMN password_gate_enabled TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'If 1 and access_password set, enable Screen 1c (password gate).'
    AFTER access_password;

-- Backfill: preserve existing behavior for bots that already had gate source fields populated.
UPDATE bots SET channel_gate_enabled  = 1 WHERE linked_channel_id     IS NOT NULL AND linked_channel_id     <> '';
UPDATE bots SET reg_gate_enabled      = 1 WHERE referral_url_template IS NOT NULL AND referral_url_template <> '';
UPDATE bots SET password_gate_enabled = 1 WHERE access_password       IS NOT NULL AND access_password       <> '';
-- deposit_gate_enabled stays 0 (admin opt-in, by design).
