-- Migration 012: Log every incoming postback, even rejected ones.
-- Adds auth_status column, relaxes bot_id and event_type to NULL so we can log
-- requests that fail validation (missing params, unknown bot, bad secret).

ALTER TABLE postback_events
    MODIFY COLUMN bot_id INT NULL,
    MODIFY COLUMN event_type VARCHAR(50) NULL;

ALTER TABLE postback_events
    ADD COLUMN auth_status VARCHAR(30) NOT NULL DEFAULT 'ok'
    COMMENT 'ok | bad_secret | unknown_bot | missing_params'
    AFTER event_type;
