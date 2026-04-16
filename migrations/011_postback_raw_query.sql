-- Migration 011: Add raw_query column to postback_events.
-- Stores the original query string (with secret redacted) so we retain the exact
-- form the affiliate sent — useful when debugging encoding/escaping issues that
-- params_json may have normalised away.

ALTER TABLE postback_events
    ADD COLUMN raw_query TEXT NULL
    COMMENT 'Original raw query string as received (secret redacted)'
    AFTER params_json;
