-- Migration 005: Seed base Russian translations
-- Created: 2026-04-13
-- bot_id = NULL means base/shared defaults

INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
-- Screen 1: Language Selection
(NULL, 'lang_select.title', 'ru', 'Выберите язык'),

-- Screen 1a: Channel Check
(NULL, 'channel.required', 'ru', '⚠️ Для использования бота необходимо подписаться на канал:'),
(NULL, 'channel.btn_subscribe', 'ru', '📢 Подписаться на канал'),
(NULL, 'channel.btn_check', 'ru', '✅ Я подписался'),
(NULL, 'channel.not_subscribed', 'ru', '❌ Вы ещё не подписаны на канал. Подпишитесь и попробуйте снова.'),

-- Screen 2: Main Menu
(NULL, 'main_menu.title', 'ru', 'Главное меню:'),
(NULL, 'main_menu.btn_instruction', 'ru', '📚 Инструкция'),
(NULL, 'main_menu.btn_language', 'ru', '🌐 Выбрать язык'),
(NULL, 'main_menu.btn_support', 'ru', '🔗 Поддержка'),
(NULL, 'main_menu.btn_signal', 'ru', '📊 Получить сигнал'),
(NULL, 'main_menu.btn_admin', 'ru', '⚙️ Админ-панель'),

-- Screen 3: Instructions
(NULL, 'instruction.intro', 'ru', '🤖 Бот основан и обучен на кластерной нейронной сети OpenTrandAI!'),
(NULL, 'instruction.training', 'ru', '👨‍💻 Для обучения бота было проанализировано более 3 миллионов трейдовых событий. В настоящее время пользователи бота успешно генерируют 5-25% от своего капитала ежедневно!'),
(NULL, 'instruction.accuracy', 'ru', 'Бот всё ещё в процессе обучения, доработки, несмотря на это точность анализа находится на достаточно высоком уровне! Чтобы достичь максимальную прибыль, следуйте этой инструкции:'),
(NULL, 'instruction.step1', 'ru', '1️⃣ Зарегистрируйтесь на сайте Pocket Option'),
(NULL, 'instruction.step2', 'ru', '2️⃣ Пополните баланс своего счёта.'),
(NULL, 'instruction.step3', 'ru', '3️⃣ Перейдите в раздел реальной торговли.'),
(NULL, 'instruction.step4', 'ru', '4️⃣ Выберите валютную пару на сайте, а так же время экспирации'),
(NULL, 'instruction.step5', 'ru', '5️⃣ Загрузите анализ в бота и торгуйте следуя его анализу.'),
(NULL, 'instruction.warning', 'ru', '⚠️ В случае неудачного сигнала рекомендуем удвоить сумму (максимум 3 раза подряд, в случае неудачи, переждать не входя валютную пару), тобы достичь прибыль с помощью следующего сигнала.'),
(NULL, 'instruction.no_access', 'ru', '⛔ Без регистрации и промокода доступ к сигналам не будет открыт'),
(NULL, 'instruction.btn_back', 'ru', '↩️ Вернуться в главное меню'),

-- Screen 4: Web App
(NULL, 'webapp.header', 'ru', 'Торговые Сигналы'),
(NULL, 'webapp.subtitle', 'ru', 'Профессиональные сигналы для бинарных опционов'),
(NULL, 'webapp.tab_forex', 'ru', 'Форекс'),
(NULL, 'webapp.tab_otc', 'ru', 'OTC'),
(NULL, 'webapp.currency_pair_label', 'ru', 'ВАЛЮТНАЯ ПАРА'),
(NULL, 'webapp.currency_pair_placeholder', 'ru', 'Начните вводить, напр. EUR/USD'),
(NULL, 'webapp.expiration_label', 'ru', 'ВРЕМЯ ЭКСПИРАЦИИ'),
(NULL, 'webapp.expiration_1m', 'ru', '1 минута'),
(NULL, 'webapp.expiration_3m', 'ru', '3 минуты'),
(NULL, 'webapp.expiration_5m', 'ru', '5 минут'),
(NULL, 'webapp.expiration_15m', 'ru', '15 минут'),
(NULL, 'webapp.expiration_30m', 'ru', '30 минут'),
(NULL, 'webapp.btn_get_signal', 'ru', '⚡ Получить сигнал'),
(NULL, 'webapp.signal_generated', 'ru', 'Сигнал сгенерирован!'),
(NULL, 'webapp.signal_direction_label', 'ru', 'НАПРАВЛЕНИЕ СИГНАЛА'),
(NULL, 'webapp.signal_direction_buy', 'ru', 'Покупать'),
(NULL, 'webapp.signal_direction_sell', 'ru', 'Продавать'),
(NULL, 'webapp.signal_confidence_label', 'ru', 'УРОВЕНЬ УВЕРЕННОСТИ'),
(NULL, 'webapp.signal_confidence_low', 'ru', 'Низкий'),
(NULL, 'webapp.signal_confidence_medium', 'ru', 'Средний'),
(NULL, 'webapp.signal_confidence_high', 'ru', 'Высокий'),
(NULL, 'webapp.chart_header', 'ru', 'График рынка'),
(NULL, 'webapp.chart_subtitle', 'ru', 'График от TradingView'),

-- Screen 5: Admin Menu
(NULL, 'admin.title', 'ru', 'Админ-панель:'),
(NULL, 'admin.btn_broadcast', 'ru', '📊 Рассылка'),
(NULL, 'admin.btn_stats', 'ru', '📈 Статистика'),
(NULL, 'admin.btn_referral', 'ru', '🔗 Смена реферальной ссылки'),
(NULL, 'admin.btn_promo', 'ru', '📋 Промокоды'),
(NULL, 'admin.btn_back', 'ru', '↩️ Вернуться в главное меню'),

-- Screen 5a: Statistics
(NULL, 'admin.stats_header', 'ru', '📊 Статистика бота'),
(NULL, 'admin.stats_total', 'ru', '👥 Всего пользователей: {count}'),
(NULL, 'admin.stats_registered', 'ru', '✅ Прошли регистрацию: {count}'),
(NULL, 'admin.stats_deposited', 'ru', '💰 Сделали первый депозит: {count}'),

-- Screen 5b: Broadcast
(NULL, 'admin.broadcast_prompt', 'ru', 'Перешлите или отправьте сообщение для рассылки:'),
(NULL, 'admin.broadcast_audience', 'ru', '📢 Кому отправить рассылку?'),
(NULL, 'admin.broadcast_all', 'ru', '👥 Всем'),
(NULL, 'admin.broadcast_registered', 'ru', '✅ Прошли регистрацию'),
(NULL, 'admin.broadcast_deposited', 'ru', '💸 Сделали депозит'),
(NULL, 'admin.broadcast_confirm', 'ru', '📨 Сообщение для рассылки получено.\nОтправить? ({count} чел.)'),
(NULL, 'admin.broadcast_btn_send', 'ru', '✅ Отправить'),
(NULL, 'admin.broadcast_btn_cancel', 'ru', '❌ Отмена'),
(NULL, 'admin.broadcast_done', 'ru', '✅ Рассылка завершена!\nОтправлено: {success}/{total}'),
(NULL, 'admin.broadcast_cancelled', 'ru', '❌ Рассылка отменена.'),
(NULL, 'admin.btn_back_admin', 'ru', '↩️ Вернуться в админ-панель'),

-- Admin Notifications
(NULL, 'notify.channel_no_permission', 'ru', '⚠️ Бот не имеет прав администратора в канале {channel}. Проверка подписки пропущена.'),
(NULL, 'notify.bot_started', 'ru', 'ℹ️ Бот запущен (bot_id={id})'),
(NULL, 'notify.bot_stopped', 'ru', 'ℹ️ Бот остановлен'),
(NULL, 'notify.db_error', 'ru', '🚨 Ошибка подключения к БД: {error}'),
(NULL, 'notify.unhandled_error', 'ru', '🚨 Необработанная ошибка: {error}'),

-- Common
(NULL, 'common.btn_back', 'ru', '↩️ Назад'),
(NULL, 'common.error', 'ru', 'Произошла ошибка. Попробуйте позже.'),
(NULL, 'common.loading', 'ru', 'Загрузка...');
