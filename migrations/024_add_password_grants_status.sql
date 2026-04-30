-- Migration 024: Per-bot toggle for what status a successful password entry grants.
-- Fixes the case where a bot has BOTH password_gate_enabled=1 AND deposit_gate_enabled=1:
-- previously the password handler hardcoded users.status='registered', so the user was still
-- blocked by the deposit gate. With this column the admin chooses whether the password
-- grants 'registered' (deposit gate still blocks) or 'deposited' (password = full access).
-- Default is 'deposited' — that's the common case ("password = bypass everything").
-- See docs/bot-flow.md → "Screen 1c: Password Gate" for behaviour, and the registration/
-- deposit gate sections for how each gate now passes on EITHER postback presence OR
-- users.status reaching the required level.
-- Created: 2026-04-30

ALTER TABLE bots
    ADD COLUMN password_grants_status ENUM('registered','deposited') NOT NULL DEFAULT 'deposited'
    COMMENT 'Status set on users.status when a user enters access_password correctly. "deposited" = password bypasses both reg and deposit gates; "registered" = deposit gate still applies.'
    AFTER access_password;
