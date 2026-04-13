-- Migration 006: Seed currency pairs (shared, bot_id = NULL)
-- Created: 2026-04-13

-- Forex pairs (22)
INSERT INTO currency_pairs (bot_id, symbol, type, sort_order) VALUES
(NULL, 'AUD/CAD', 'forex', 1),
(NULL, 'AUD/CHF', 'forex', 2),
(NULL, 'AUD/JPY', 'forex', 3),
(NULL, 'AUD/USD', 'forex', 4),
(NULL, 'CAD/CHF', 'forex', 5),
(NULL, 'CAD/JPY', 'forex', 6),
(NULL, 'CHF/JPY', 'forex', 7),
(NULL, 'EUR/AUD', 'forex', 8),
(NULL, 'EUR/CAD', 'forex', 9),
(NULL, 'EUR/CHF', 'forex', 10),
(NULL, 'EUR/GBP', 'forex', 11),
(NULL, 'EUR/JPY', 'forex', 12),
(NULL, 'EUR/USD', 'forex', 13),
(NULL, 'GBP/CAD', 'forex', 14),
(NULL, 'GBP/CHF', 'forex', 15),
(NULL, 'GBP/JPY', 'forex', 16),
(NULL, 'GBP/USD', 'forex', 17),
(NULL, 'NZD/JPY', 'forex', 18),
(NULL, 'NZD/USD', 'forex', 19),
(NULL, 'USD/CAD', 'forex', 20),
(NULL, 'USD/CHF', 'forex', 21),
(NULL, 'USD/JPY', 'forex', 22);

-- OTC pairs (partial list from screenshots)
INSERT INTO currency_pairs (bot_id, symbol, type, sort_order) VALUES
(NULL, 'OTC NZD/USD', 'otc', 1),
(NULL, 'OTC TND/USD', 'otc', 2),
(NULL, 'OTC UAH/USD', 'otc', 3),
(NULL, 'OTC USD/ARS', 'otc', 4),
(NULL, 'OTC USD/BRL', 'otc', 5),
(NULL, 'OTC USD/CAD', 'otc', 6),
(NULL, 'OTC USD/CHF', 'otc', 7);
