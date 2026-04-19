-- Migration 023: Base translations for the deposit gate (Screen 1d).
-- Keys seeded (bot_id = NULL = base defaults; per-bot overrides may be added later):
--   deposit.required     — prompt shown when deposit gate is active (Screen 1d)
--   deposit.btn_deposit  — "Make deposit" inline button (opens referral_url_template as deposit URL)
--   deposit.btn_check    — "I made a deposit" inline button (triggers postback re-check)
--   deposit.btn_support  — "Support" inline button (link to support contact)
--   deposit.not_yet      — ephemeral alert shown when the postback has not confirmed a deposit yet
-- See docs/bot-flow.md → "Screen 1d" and "Translation keys for the deposit gate".
-- Created: 2026-04-19

-- deposit.required
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'deposit.required', 'en', '💰 To access signals, please make a deposit:'),
(NULL, 'deposit.required', 'ru', '💰 Для доступа к сигналам сделайте депозит:'),
(NULL, 'deposit.required', 'es', '💰 Para acceder a las señales, realiza un depósito:'),
(NULL, 'deposit.required', 'ar', '💰 للوصول إلى الإشارات، يرجى إجراء إيداع:'),
(NULL, 'deposit.required', 'pt', '💰 Para acessar os sinais, faça um depósito:'),
(NULL, 'deposit.required', 'tr', '💰 Sinyallere erişmek için lütfen para yatırın:'),
(NULL, 'deposit.required', 'hi', '💰 सिग्नल तक पहुँच के लिए, कृपया जमा करें:'),
(NULL, 'deposit.required', 'uz', '💰 Signallardan foydalanish uchun depozit kiriting:'),
(NULL, 'deposit.required', 'az', '💰 Siqnallara çıxış üçün depozit edin:'),
(NULL, 'deposit.required', 'tg', '💰 Барои дастрасӣ ба сигналҳо депозит кунед:'),
(NULL, 'deposit.required', 'ko', '💰 신호에 접근하려면 입금해 주세요:');

-- deposit.btn_deposit
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'deposit.btn_deposit', 'en', '💳 Make deposit'),
(NULL, 'deposit.btn_deposit', 'ru', '💳 Сделать депозит'),
(NULL, 'deposit.btn_deposit', 'es', '💳 Hacer depósito'),
(NULL, 'deposit.btn_deposit', 'ar', '💳 إجراء إيداع'),
(NULL, 'deposit.btn_deposit', 'pt', '💳 Fazer depósito'),
(NULL, 'deposit.btn_deposit', 'tr', '💳 Para yatır'),
(NULL, 'deposit.btn_deposit', 'hi', '💳 जमा करें'),
(NULL, 'deposit.btn_deposit', 'uz', '💳 Depozit kiritish'),
(NULL, 'deposit.btn_deposit', 'az', '💳 Depozit et'),
(NULL, 'deposit.btn_deposit', 'tg', '💳 Депозит кардан'),
(NULL, 'deposit.btn_deposit', 'ko', '💳 입금하기');

-- deposit.btn_check
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'deposit.btn_check', 'en', '✅ I made a deposit'),
(NULL, 'deposit.btn_check', 'ru', '✅ Я сделал депозит'),
(NULL, 'deposit.btn_check', 'es', '✅ Hice un depósito'),
(NULL, 'deposit.btn_check', 'ar', '✅ لقد أجريت إيداعًا'),
(NULL, 'deposit.btn_check', 'pt', '✅ Fiz um depósito'),
(NULL, 'deposit.btn_check', 'tr', '✅ Para yatırdım'),
(NULL, 'deposit.btn_check', 'hi', '✅ मैंने जमा किया'),
(NULL, 'deposit.btn_check', 'uz', '✅ Men depozit kiritdim'),
(NULL, 'deposit.btn_check', 'az', '✅ Mən depozit etdim'),
(NULL, 'deposit.btn_check', 'tg', '✅ Ман депозит кардам'),
(NULL, 'deposit.btn_check', 'ko', '✅ 입금했습니다');

-- deposit.btn_support
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'deposit.btn_support', 'en', '🔗 Support'),
(NULL, 'deposit.btn_support', 'ru', '🔗 Поддержка'),
(NULL, 'deposit.btn_support', 'es', '🔗 Soporte'),
(NULL, 'deposit.btn_support', 'ar', '🔗 الدعم'),
(NULL, 'deposit.btn_support', 'pt', '🔗 Suporte'),
(NULL, 'deposit.btn_support', 'tr', '🔗 Destek'),
(NULL, 'deposit.btn_support', 'hi', '🔗 सहायता'),
(NULL, 'deposit.btn_support', 'uz', '🔗 Qo‘llab-quvvatlash'),
(NULL, 'deposit.btn_support', 'az', '🔗 Dəstək'),
(NULL, 'deposit.btn_support', 'tg', '🔗 Дастгирӣ'),
(NULL, 'deposit.btn_support', 'ko', '🔗 지원');

-- deposit.not_yet
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'deposit.not_yet', 'en', '⏳ We haven''t received your deposit yet.'),
(NULL, 'deposit.not_yet', 'ru', '⏳ Мы ещё не получили ваш депозит.'),
(NULL, 'deposit.not_yet', 'es', '⏳ Aún no hemos recibido tu depósito.'),
(NULL, 'deposit.not_yet', 'ar', '⏳ لم نستلم إيداعك بعد.'),
(NULL, 'deposit.not_yet', 'pt', '⏳ Ainda não recebemos o seu depósito.'),
(NULL, 'deposit.not_yet', 'tr', '⏳ Para yatırmanızı henüz almadık.'),
(NULL, 'deposit.not_yet', 'hi', '⏳ हमें अभी तक आपका जमा नहीं मिला है।'),
(NULL, 'deposit.not_yet', 'uz', '⏳ Depozitingiz hali kelib tushmadi.'),
(NULL, 'deposit.not_yet', 'az', '⏳ Depozitiniz hələ daxil olmayıb.'),
(NULL, 'deposit.not_yet', 'tg', '⏳ Депозити шумо ҳанӯз нарасидааст.'),
(NULL, 'deposit.not_yet', 'ko', '⏳ 아직 입금이 확인되지 않았습니다.');
