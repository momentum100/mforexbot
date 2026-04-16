-- Migration 018: Multi-bot launcher support.
-- Adds bots.is_active flag and a single-row `system` table used by the
-- launcher to detect an admin-requested restart.
-- Created: 2026-04-15

ALTER TABLE bots
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS system (
    id TINYINT NOT NULL PRIMARY KEY DEFAULT 1,
    restart_requested_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT system_singleton CHECK (id = 1)
);

INSERT IGNORE INTO system (id, restart_requested_at) VALUES (1, NULL);
