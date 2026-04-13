-- Migration 003: Seed admin users for first bot
-- Created: 2026-04-13

INSERT INTO users (bot_id, telegram_id, is_admin) VALUES
(1, 51337503, 1),
(1, 7440384100, 1);
