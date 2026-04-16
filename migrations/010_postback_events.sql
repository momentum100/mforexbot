-- Migration 010: Append-only postback events log.
-- Replaces the earlier users.status ENUM approach. Every incoming postback is
-- logged as a row; downstream checks (e.g. registration gate) query this table
-- instead of maintaining per-user status columns.

CREATE TABLE IF NOT EXISTS postback_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    telegram_id BIGINT NULL COMMENT 'Parsed from click_id; NULL if missing/unknown',
    event_type VARCHAR(50) NOT NULL COMMENT 'Raw event name from affiliate (reg, ftd, redep, commission, withdrawal, ...)',
    params_json JSON NOT NULL COMMENT 'Full query string — nothing is lost',
    ip VARCHAR(45) NULL,
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_event (bot_id, telegram_id, event_type),
    INDEX idx_received (received_at),
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
