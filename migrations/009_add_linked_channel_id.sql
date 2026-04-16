-- Migration 009: Split linked_channel into URL (for subscribe button) + numeric ID (for API).
-- linked_channel now holds @username or https://t.me/... (used for the subscribe button URL).
-- linked_channel_id holds the numeric chat_id (e.g. -1001234567890) used for getChatMember API calls.

ALTER TABLE bots
    ADD COLUMN linked_channel_id VARCHAR(50) NULL
    COMMENT 'Numeric Telegram chat_id (e.g. -1001234567890) used for getChatMember API calls'
    AFTER linked_channel;
