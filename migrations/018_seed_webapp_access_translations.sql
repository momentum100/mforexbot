-- Migration 018: Base translations for the webapp access-denied stub page.
-- Run with: mysql --default-character-set=utf8mb4 < 018_seed_webapp_access_translations.sql
-- Keys seeded (bot_id = NULL = base defaults):
--   webapp.access_denied_title      — stub heading
--   webapp.access_denied_text       — explanation paragraph
--   webapp.access_denied_subscribe  — step: subscribe to channel
--   webapp.access_denied_register   — step: complete registration
--   webapp.access_denied_support    — step: contact support
--   webapp.access_denied_btn_support — support button label
--   webapp.access_denied_btn_retry   — retry button label
-- Created: 2026-04-15

-- webapp.access_denied_title
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'webapp.access_denied_title', 'en', 'Access restricted'),
(NULL, 'webapp.access_denied_title', 'ru', 'Доступ ограничен'),
(NULL, 'webapp.access_denied_title', 'es', 'Acceso restringido'),
(NULL, 'webapp.access_denied_title', 'ar', 'الوصول مقيد'),
(NULL, 'webapp.access_denied_title', 'pt', 'Acesso restrito'),
(NULL, 'webapp.access_denied_title', 'tr', 'Erişim kısıtlı'),
(NULL, 'webapp.access_denied_title', 'hi', 'पहुँच प्रतिबंधित'),
(NULL, 'webapp.access_denied_title', 'uz', 'Kirish cheklangan'),
(NULL, 'webapp.access_denied_title', 'az', 'Giriş məhdudlaşdırılıb'),
(NULL, 'webapp.access_denied_title', 'tg', 'Дастрасӣ маҳдуд аст'),
(NULL, 'webapp.access_denied_title', 'ko', '접근이 제한되었습니다');

-- webapp.access_denied_text
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'webapp.access_denied_text', 'en', 'To use the signal service, complete the following steps in the bot menu:'),
(NULL, 'webapp.access_denied_text', 'ru', 'Для использования сервиса сигналов выполните следующие действия в меню бота:'),
(NULL, 'webapp.access_denied_text', 'es', 'Para usar el servicio de señales, completa los siguientes pasos en el menú del bot:'),
(NULL, 'webapp.access_denied_text', 'ar', 'لاستخدام خدمة الإشارات، أكمل الخطوات التالية في قائمة البوت:'),
(NULL, 'webapp.access_denied_text', 'pt', 'Para usar o serviço de sinais, complete as seguintes etapas no menu do bot:'),
(NULL, 'webapp.access_denied_text', 'tr', 'Sinyal hizmetini kullanmak için bot menüsünde aşağıdaki adımları tamamlayın:'),
(NULL, 'webapp.access_denied_text', 'hi', 'सिग्नल सेवा का उपयोग करने के लिए, बॉट मेनू में निम्नलिखित चरण पूरे करें:'),
(NULL, 'webapp.access_denied_text', 'uz', 'Signal xizmatidan foydalanish uchun bot menyusida quyidagi qadamlarni bajaring:'),
(NULL, 'webapp.access_denied_text', 'az', 'Siqnal xidmətindən istifadə etmək üçün bot menyusunda aşağıdakı addımları tamamlayın:'),
(NULL, 'webapp.access_denied_text', 'tg', 'Барои истифодаи хизмати сигналҳо, қадамҳои зеринро дар менюи бот иҷро кунед:'),
(NULL, 'webapp.access_denied_text', 'ko', '시그널 서비스를 이용하려면 봇 메뉴에서 다음 단계를 완료하세요:');

-- webapp.access_denied_subscribe
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'webapp.access_denied_subscribe', 'en', 'Subscribe to the bot channel'),
(NULL, 'webapp.access_denied_subscribe', 'ru', 'Подпишитесь на канал бота'),
(NULL, 'webapp.access_denied_subscribe', 'es', 'Suscríbete al canal del bot'),
(NULL, 'webapp.access_denied_subscribe', 'ar', 'اشترك في قناة البوت'),
(NULL, 'webapp.access_denied_subscribe', 'pt', 'Inscreva-se no canal do bot'),
(NULL, 'webapp.access_denied_subscribe', 'tr', 'Bot kanalına abone olun'),
(NULL, 'webapp.access_denied_subscribe', 'hi', 'बॉट चैनल को सब्सक्राइब करें'),
(NULL, 'webapp.access_denied_subscribe', 'uz', 'Bot kanaliga obuna bo\'ling'),
(NULL, 'webapp.access_denied_subscribe', 'az', 'Bot kanalına abunə olun'),
(NULL, 'webapp.access_denied_subscribe', 'tg', 'Ба канали бот обуна шавед'),
(NULL, 'webapp.access_denied_subscribe', 'ko', '봇 채널을 구독하세요');

-- webapp.access_denied_register
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'webapp.access_denied_register', 'en', 'Complete registration or enter access code'),
(NULL, 'webapp.access_denied_register', 'ru', 'Пройдите регистрацию или введите код доступа'),
(NULL, 'webapp.access_denied_register', 'es', 'Complete el registro o introduzca el código de acceso'),
(NULL, 'webapp.access_denied_register', 'ar', 'أكمل التسجيل أو أدخل رمز الدخول'),
(NULL, 'webapp.access_denied_register', 'pt', 'Complete o registro ou insira o código de acesso'),
(NULL, 'webapp.access_denied_register', 'tr', 'Kaydı tamamlayın veya erişim kodunu girin'),
(NULL, 'webapp.access_denied_register', 'hi', 'पंजीकरण पूरा करें या एक्सेस कोड दर्ज करें'),
(NULL, 'webapp.access_denied_register', 'uz', 'Ro\'yxatdan o\'ting yoki kirish kodini kiriting'),
(NULL, 'webapp.access_denied_register', 'az', 'Qeydiyyatı tamamlayın və ya giriş kodunu daxil edin'),
(NULL, 'webapp.access_denied_register', 'tg', 'Сабтномро анҷом диҳед ё рамзи дастрасиро ворид кунед'),
(NULL, 'webapp.access_denied_register', 'ko', '등록을 완료하거나 액세스 코드를 입력하세요');

-- webapp.access_denied_support
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'webapp.access_denied_support', 'en', 'Or contact support'),
(NULL, 'webapp.access_denied_support', 'ru', 'Или напишите в поддержку'),
(NULL, 'webapp.access_denied_support', 'es', 'O contacte con soporte'),
(NULL, 'webapp.access_denied_support', 'ar', 'أو تواصل مع الدعم'),
(NULL, 'webapp.access_denied_support', 'pt', 'Ou entre em contato com o suporte'),
(NULL, 'webapp.access_denied_support', 'tr', 'Veya destek ile iletişime geçin'),
(NULL, 'webapp.access_denied_support', 'hi', 'या सहायता से संपर्क करें'),
(NULL, 'webapp.access_denied_support', 'uz', 'Yoki qo\'llab-quvvatlash xizmatiga murojaat qiling'),
(NULL, 'webapp.access_denied_support', 'az', 'Və ya dəstək ilə əlaqə saxlayın'),
(NULL, 'webapp.access_denied_support', 'tg', 'Ё ба дастгирӣ муроҷиат кунед'),
(NULL, 'webapp.access_denied_support', 'ko', '또는 고객 지원에 문의하세요');

-- webapp.access_denied_btn_support
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'webapp.access_denied_btn_support', 'en', '💬 Support'),
(NULL, 'webapp.access_denied_btn_support', 'ru', '💬 Поддержка'),
(NULL, 'webapp.access_denied_btn_support', 'es', '💬 Soporte'),
(NULL, 'webapp.access_denied_btn_support', 'ar', '💬 الدعم'),
(NULL, 'webapp.access_denied_btn_support', 'pt', '💬 Suporte'),
(NULL, 'webapp.access_denied_btn_support', 'tr', '💬 Destek'),
(NULL, 'webapp.access_denied_btn_support', 'hi', '💬 सहायता'),
(NULL, 'webapp.access_denied_btn_support', 'uz', '💬 Yordam'),
(NULL, 'webapp.access_denied_btn_support', 'az', '💬 Dəstək'),
(NULL, 'webapp.access_denied_btn_support', 'tg', '💬 Дастгирӣ'),
(NULL, 'webapp.access_denied_btn_support', 'ko', '💬 지원');

-- webapp.access_denied_btn_retry
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'webapp.access_denied_btn_retry', 'en', '🔄 Check again'),
(NULL, 'webapp.access_denied_btn_retry', 'ru', '🔄 Проверить снова'),
(NULL, 'webapp.access_denied_btn_retry', 'es', '🔄 Verificar de nuevo'),
(NULL, 'webapp.access_denied_btn_retry', 'ar', '🔄 تحقق مرة أخرى'),
(NULL, 'webapp.access_denied_btn_retry', 'pt', '🔄 Verificar novamente'),
(NULL, 'webapp.access_denied_btn_retry', 'tr', '🔄 Tekrar kontrol et'),
(NULL, 'webapp.access_denied_btn_retry', 'hi', '🔄 फिर से जांचें'),
(NULL, 'webapp.access_denied_btn_retry', 'uz', '🔄 Qayta tekshirish'),
(NULL, 'webapp.access_denied_btn_retry', 'az', '🔄 Yenidən yoxla'),
(NULL, 'webapp.access_denied_btn_retry', 'tg', '🔄 Бори дигар тафтиш кунед'),
(NULL, 'webapp.access_denied_btn_retry', 'ko', '🔄 다시 확인');
