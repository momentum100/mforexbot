-- Migration 020: Base translations for registration congratulations notification.
-- Key: postback.reg_congrats — sent via Telegram when a successful `reg` postback arrives.
-- Contains {support_link} placeholder replaced at runtime with bots.support_link.
-- charset: utf8mb4
-- Created: 2026-04-15

INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'postback.reg_congrats', 'en', '🎉 Congratulations on your registration! Contact support {support_link} to receive your personal promo code!'),
(NULL, 'postback.reg_congrats', 'ru', '🎉 Поздравляем с регистрацией! Напишите в поддержку {support_link} для получения индивидуального промо-кода!'),
(NULL, 'postback.reg_congrats', 'es', '🎉 ¡Felicidades por tu registro! Escribe al soporte {support_link} para recibir tu código promocional personal.'),
(NULL, 'postback.reg_congrats', 'ar', '🎉 تهانينا بالتسجيل! تواصل مع الدعم {support_link} للحصول على رمز ترويجي خاص بك!'),
(NULL, 'postback.reg_congrats', 'pt', '🎉 Parabéns pelo cadastro! Entre em contato com o suporte {support_link} para receber seu código promocional!'),
(NULL, 'postback.reg_congrats', 'tr', '🎉 Kayıt olduğunuz için tebrikler! Kişisel promosyon kodunuz için destek ile iletişime geçin: {support_link}'),
(NULL, 'postback.reg_congrats', 'hi', '🎉 रजिस्ट्रेशन की बधाई! अपना व्यक्तिगत प्रोमो कोड पाने के लिए सपोर्ट से संपर्क करें {support_link}'),
(NULL, 'postback.reg_congrats', 'uz', '🎉 Ro''yxatdan o''tganingiz bilan tabriklaymiz! Shaxsiy promo-kod olish uchun qo''llab-quvvatlash xizmatiga yozing: {support_link}'),
(NULL, 'postback.reg_congrats', 'az', '🎉 Qeydiyyat üçün təbriklər! Fərdi promo kodunuzu almaq üçün dəstəklə əlaqə saxlayın: {support_link}'),
(NULL, 'postback.reg_congrats', 'tg', '🎉 Бо сабти ном табрик мекунем! Барои гирифтани рамзи промо шахсӣ ба дастгирӣ нависед: {support_link}'),
(NULL, 'postback.reg_congrats', 'ko', '🎉 가입을 축하합니다! 개인 프로모 코드를 받으려면 고객 지원에 문의하세요: {support_link}');
