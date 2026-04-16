-- Migration 014: Translations for the forex-market-closed notice shown in the webapp.
-- Created: 2026-04-14

INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'webapp.forex_closed_note', 'en', 'Forex market is closed — try OTC'),
(NULL, 'webapp.forex_closed_note', 'ru', 'Форекс закрыт — выберите OTC'),
(NULL, 'webapp.forex_closed_note', 'es', 'El mercado Forex está cerrado — prueba OTC'),
(NULL, 'webapp.forex_closed_note', 'ar', 'سوق الفوركس مغلق — جرّب OTC'),
(NULL, 'webapp.forex_closed_note', 'pt', 'Mercado Forex fechado — tente OTC'),
(NULL, 'webapp.forex_closed_note', 'tr', 'Forex piyasası kapalı — OTC deneyin'),
(NULL, 'webapp.forex_closed_note', 'hi', 'Forex बाजार बंद है — OTC आज़माएँ'),
(NULL, 'webapp.forex_closed_note', 'uz', 'Forex yopiq — OTC ni tanlang'),
(NULL, 'webapp.forex_closed_note', 'az', 'Forex bağlıdır — OTC seçin'),
(NULL, 'webapp.forex_closed_note', 'tg', 'Форекс баста аст — OTC-ро интихоб кунед'),
(NULL, 'webapp.forex_closed_note', 'ko', '포렉스 시장 마감 — OTC를 이용하세요');
