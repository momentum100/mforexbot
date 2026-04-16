-- Migration 016: Base translations for the password / combined registration gate.
-- Keys seeded (bot_id = NULL = base defaults; per-bot overrides may be added later):
--   password.required           — prompt shown when password gate is active (standalone or after
--                                 tapping "Enter access code" on the combined gate)
--   password.wrong              — ephemeral alert on mismatch
--   password.btn_cancel         — Cancel inline button label
--   password.cancelled          — confirmation after Cancel
--   register.btn_enter_password — extra button label on the combined gate (Screen 1b) that opens
--                                 the password FSM
--   register.combined_required  — prompt shown on the combined gate when BOTH referral_url_template
--                                 and access_password are set
-- See docs/bot-flow.md → "Screen 1b" (combined gate) and "Screen 1c: Password Gate".
-- Created: 2026-04-15

-- password.required
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'password.required', 'en', 'Enter access code:'),
(NULL, 'password.required', 'ru', 'Введите код доступа:'),
(NULL, 'password.required', 'es', 'Introduce el código de acceso:'),
(NULL, 'password.required', 'ar', 'أدخل رمز الدخول:'),
(NULL, 'password.required', 'pt', 'Digite o código de acesso:'),
(NULL, 'password.required', 'tr', 'Erişim kodunu girin:'),
(NULL, 'password.required', 'hi', 'एक्सेस कोड दर्ज करें:'),
(NULL, 'password.required', 'uz', 'Kirish kodini kiriting:'),
(NULL, 'password.required', 'az', 'Giriş kodunu daxil edin:'),
(NULL, 'password.required', 'tg', 'Рамзи дастрасиро ворид кунед:'),
(NULL, 'password.required', 'ko', '액세스 코드를 입력하세요:');

-- password.wrong
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'password.wrong', 'en', '❌ Wrong code. Try again.'),
(NULL, 'password.wrong', 'ru', '❌ Неверный код. Попробуйте ещё раз.'),
(NULL, 'password.wrong', 'es', '❌ Código incorrecto. Inténtalo de nuevo.'),
(NULL, 'password.wrong', 'ar', '❌ رمز غير صحيح. حاول مرة أخرى.'),
(NULL, 'password.wrong', 'pt', '❌ Código incorreto. Tente novamente.'),
(NULL, 'password.wrong', 'tr', '❌ Yanlış kod. Tekrar deneyin.'),
(NULL, 'password.wrong', 'hi', '❌ ग़लत कोड। पुनः प्रयास करें।'),
(NULL, 'password.wrong', 'uz', '❌ Kod noto‘g‘ri. Qayta urinib ko‘ring.'),
(NULL, 'password.wrong', 'az', '❌ Kod yanlışdır. Yenidən cəhd edin.'),
(NULL, 'password.wrong', 'tg', '❌ Рамз нодуруст. Бори дигар кӯшиш кунед.'),
(NULL, 'password.wrong', 'ko', '❌ 코드가 올바르지 않습니다. 다시 시도하세요.');

-- password.btn_cancel
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'password.btn_cancel', 'en', '↩️ Cancel'),
(NULL, 'password.btn_cancel', 'ru', '↩️ Отмена'),
(NULL, 'password.btn_cancel', 'es', '↩️ Cancelar'),
(NULL, 'password.btn_cancel', 'ar', '↩️ إلغاء'),
(NULL, 'password.btn_cancel', 'pt', '↩️ Cancelar'),
(NULL, 'password.btn_cancel', 'tr', '↩️ İptal'),
(NULL, 'password.btn_cancel', 'hi', '↩️ रद्द करें'),
(NULL, 'password.btn_cancel', 'uz', '↩️ Bekor qilish'),
(NULL, 'password.btn_cancel', 'az', '↩️ Ləğv et'),
(NULL, 'password.btn_cancel', 'tg', '↩️ Бекор кардан'),
(NULL, 'password.btn_cancel', 'ko', '↩️ 취소');

-- password.cancelled
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'password.cancelled', 'en', 'Cancelled.'),
(NULL, 'password.cancelled', 'ru', 'Отменено.'),
(NULL, 'password.cancelled', 'es', 'Cancelado.'),
(NULL, 'password.cancelled', 'ar', 'تم الإلغاء.'),
(NULL, 'password.cancelled', 'pt', 'Cancelado.'),
(NULL, 'password.cancelled', 'tr', 'İptal edildi.'),
(NULL, 'password.cancelled', 'hi', 'रद्द किया गया।'),
(NULL, 'password.cancelled', 'uz', 'Bekor qilindi.'),
(NULL, 'password.cancelled', 'az', 'Ləğv edildi.'),
(NULL, 'password.cancelled', 'tg', 'Бекор карда шуд.'),
(NULL, 'password.cancelled', 'ko', '취소되었습니다.');

-- register.btn_enter_password
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'register.btn_enter_password', 'en', '🔑 Enter access code'),
(NULL, 'register.btn_enter_password', 'ru', '🔑 Ввести код доступа'),
(NULL, 'register.btn_enter_password', 'es', '🔑 Introducir código de acceso'),
(NULL, 'register.btn_enter_password', 'ar', '🔑 إدخال رمز الدخول'),
(NULL, 'register.btn_enter_password', 'pt', '🔑 Inserir código de acesso'),
(NULL, 'register.btn_enter_password', 'tr', '🔑 Erişim kodunu gir'),
(NULL, 'register.btn_enter_password', 'hi', '🔑 एक्सेस कोड दर्ज करें'),
(NULL, 'register.btn_enter_password', 'uz', '🔑 Kirish kodini kiritish'),
(NULL, 'register.btn_enter_password', 'az', '🔑 Giriş kodunu daxil et'),
(NULL, 'register.btn_enter_password', 'tg', '🔑 Рамзи дастрасиро ворид кунед'),
(NULL, 'register.btn_enter_password', 'ko', '🔑 액세스 코드 입력');

-- register.combined_required
INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
(NULL, 'register.combined_required', 'en', '⚠️ To use the bot, register via the partner link or enter an access code:'),
(NULL, 'register.combined_required', 'ru', '⚠️ Для использования бота зарегистрируйтесь по ссылке партнёра или введите код доступа:'),
(NULL, 'register.combined_required', 'es', '⚠️ Para usar el bot, regístrate con el enlace del socio o introduce un código de acceso:'),
(NULL, 'register.combined_required', 'ar', '⚠️ لاستخدام البوت، سجّل عبر رابط الشريك أو أدخل رمز الدخول:'),
(NULL, 'register.combined_required', 'pt', '⚠️ Para usar o bot, registre-se pelo link do parceiro ou digite um código de acesso:'),
(NULL, 'register.combined_required', 'tr', '⚠️ Botu kullanmak için partner bağlantısıyla kayıt olun veya bir erişim kodu girin:'),
(NULL, 'register.combined_required', 'hi', '⚠️ बॉट उपयोग करने के लिए, पार्टनर लिंक से रजिस्टर करें या एक्सेस कोड दर्ज करें:'),
(NULL, 'register.combined_required', 'uz', '⚠️ Botdan foydalanish uchun hamkor havolasi orqali ro‘yxatdan o‘ting yoki kirish kodini kiriting:'),
(NULL, 'register.combined_required', 'az', '⚠️ Botdan istifadə üçün tərəfdaş linki ilə qeydiyyatdan keçin və ya giriş kodunu daxil edin:'),
(NULL, 'register.combined_required', 'tg', '⚠️ Барои истифодаи бот тавассути пайванди шарик номнавис шавед ё рамзи дастрасиро ворид кунед:'),
(NULL, 'register.combined_required', 'ko', '⚠️ 봇을 사용하려면 파트너 링크로 가입하거나 액세스 코드를 입력하세요:');
