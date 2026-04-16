-- Migration 013: Add OTC versions of all forex pairs
-- Created: 2026-04-14
-- Each forex pair gets a mirrored OTC entry so OTC mode is usable on weekends.
-- Skips symbols already seeded in migration 006: OTC NZD/USD, OTC USD/CAD, OTC USD/CHF.

INSERT INTO currency_pairs (bot_id, symbol, type, sort_order) VALUES
(NULL, 'OTC AUD/CAD', 'otc', 10),
(NULL, 'OTC AUD/CHF', 'otc', 11),
(NULL, 'OTC AUD/JPY', 'otc', 12),
(NULL, 'OTC AUD/USD', 'otc', 13),
(NULL, 'OTC CAD/CHF', 'otc', 14),
(NULL, 'OTC CAD/JPY', 'otc', 15),
(NULL, 'OTC CHF/JPY', 'otc', 16),
(NULL, 'OTC EUR/AUD', 'otc', 17),
(NULL, 'OTC EUR/CAD', 'otc', 18),
(NULL, 'OTC EUR/CHF', 'otc', 19),
(NULL, 'OTC EUR/GBP', 'otc', 20),
(NULL, 'OTC EUR/JPY', 'otc', 21),
(NULL, 'OTC EUR/USD', 'otc', 22),
(NULL, 'OTC GBP/CAD', 'otc', 23),
(NULL, 'OTC GBP/CHF', 'otc', 24),
(NULL, 'OTC GBP/JPY', 'otc', 25),
(NULL, 'OTC GBP/USD', 'otc', 26),
(NULL, 'OTC NZD/JPY', 'otc', 27),
(NULL, 'OTC USD/JPY', 'otc', 28);
