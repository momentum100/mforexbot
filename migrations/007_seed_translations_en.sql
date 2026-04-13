-- Migration 007: Seed base English translations
-- Created: 2026-04-13
-- bot_id = NULL means base/shared defaults
-- English is the ultimate fallback language

INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES
-- Screen 1: Language Selection
(NULL, 'lang_select.title', 'en', 'Select language'),

-- Screen 1a: Channel Check
(NULL, 'channel.required', 'en', '⚠️ You must subscribe to the channel to use this bot:'),
(NULL, 'channel.btn_subscribe', 'en', '📢 Subscribe to channel'),
(NULL, 'channel.btn_check', 'en', '✅ I subscribed'),
(NULL, 'channel.not_subscribed', 'en', '❌ You are not subscribed to the channel yet. Please subscribe and try again.'),

-- Screen 2: Main Menu
(NULL, 'main_menu.title', 'en', 'Main menu:'),
(NULL, 'main_menu.btn_instruction', 'en', '📚 Instructions'),
(NULL, 'main_menu.btn_language', 'en', '🌐 Change language'),
(NULL, 'main_menu.btn_support', 'en', '🔗 Support'),
(NULL, 'main_menu.btn_signal', 'en', '📊 Get signal'),
(NULL, 'main_menu.btn_admin', 'en', '⚙️ Admin panel'),

-- Screen 3: Instructions
(NULL, 'instruction.intro', 'en', '🤖 The bot is built and trained on the OpenTrandAI cluster neural network!'),
(NULL, 'instruction.training', 'en', '👨‍💻 Over 3 million trading events were analyzed to train the bot. Currently, bot users successfully generate 5-25% of their capital daily!'),
(NULL, 'instruction.accuracy', 'en', 'The bot is still in the process of learning and improvement, despite this the analysis accuracy is at a fairly high level! To achieve maximum profit, follow these instructions:'),
(NULL, 'instruction.step1', 'en', '1️⃣ Register on the Pocket Option website'),
(NULL, 'instruction.step2', 'en', '2️⃣ Fund your account balance.'),
(NULL, 'instruction.step3', 'en', '3️⃣ Go to the real trading section.'),
(NULL, 'instruction.step4', 'en', '4️⃣ Select a currency pair on the website and the expiration time'),
(NULL, 'instruction.step5', 'en', '5️⃣ Load the analysis into the bot and trade following its analysis.'),
(NULL, 'instruction.warning', 'en', '⚠️ In case of an unsuccessful signal, we recommend doubling the amount (maximum 3 times in a row, in case of failure, wait without entering the currency pair) to achieve profit with the next signal.'),
(NULL, 'instruction.no_access', 'en', '⛔ Without registration and a promo code, access to signals will not be granted'),
(NULL, 'instruction.btn_back', 'en', '↩️ Back to main menu'),

-- Screen 4: Web App
(NULL, 'webapp.header', 'en', 'Trading Signals'),
(NULL, 'webapp.subtitle', 'en', 'Professional signals for binary options'),
(NULL, 'webapp.tab_forex', 'en', 'Forex'),
(NULL, 'webapp.tab_otc', 'en', 'OTC'),
(NULL, 'webapp.currency_pair_label', 'en', 'CURRENCY PAIR'),
(NULL, 'webapp.currency_pair_placeholder', 'en', 'Start typing, e.g. EUR/USD'),
(NULL, 'webapp.expiration_label', 'en', 'EXPIRATION TIME'),
(NULL, 'webapp.expiration_1m', 'en', '1 minute'),
(NULL, 'webapp.expiration_3m', 'en', '3 minutes'),
(NULL, 'webapp.expiration_5m', 'en', '5 minutes'),
(NULL, 'webapp.expiration_15m', 'en', '15 minutes'),
(NULL, 'webapp.expiration_30m', 'en', '30 minutes'),
(NULL, 'webapp.btn_get_signal', 'en', '⚡ Get signal'),
(NULL, 'webapp.signal_generated', 'en', 'Signal generated!'),
(NULL, 'webapp.signal_direction_label', 'en', 'SIGNAL DIRECTION'),
(NULL, 'webapp.signal_direction_buy', 'en', 'Buy'),
(NULL, 'webapp.signal_direction_sell', 'en', 'Sell'),
(NULL, 'webapp.signal_confidence_label', 'en', 'CONFIDENCE LEVEL'),
(NULL, 'webapp.signal_confidence_low', 'en', 'Low'),
(NULL, 'webapp.signal_confidence_medium', 'en', 'Medium'),
(NULL, 'webapp.signal_confidence_high', 'en', 'High'),
(NULL, 'webapp.chart_header', 'en', 'Market chart'),
(NULL, 'webapp.chart_subtitle', 'en', 'Chart by TradingView'),

-- Screen 5: Admin Menu
(NULL, 'admin.title', 'en', 'Admin panel:'),
(NULL, 'admin.btn_broadcast', 'en', '📊 Broadcast'),
(NULL, 'admin.btn_stats', 'en', '📈 Statistics'),
(NULL, 'admin.btn_referral', 'en', '🔗 Change referral link'),
(NULL, 'admin.btn_promo', 'en', '📋 Promo codes'),
(NULL, 'admin.btn_back', 'en', '↩️ Back to main menu'),

-- Screen 5a: Statistics
(NULL, 'admin.stats_header', 'en', '📊 Bot statistics'),
(NULL, 'admin.stats_total', 'en', '👥 Total users: {count}'),
(NULL, 'admin.stats_registered', 'en', '✅ Registered: {count}'),
(NULL, 'admin.stats_deposited', 'en', '💰 Made first deposit: {count}'),

-- Screen 5b: Broadcast
(NULL, 'admin.broadcast_prompt', 'en', 'Forward or send a message for broadcast:'),
(NULL, 'admin.broadcast_audience', 'en', '📢 Who to send the broadcast to?'),
(NULL, 'admin.broadcast_all', 'en', '👥 Everyone'),
(NULL, 'admin.broadcast_registered', 'en', '✅ Registered users'),
(NULL, 'admin.broadcast_deposited', 'en', '💸 Users with deposit'),
(NULL, 'admin.broadcast_confirm', 'en', '📨 Broadcast message received.\nSend to all? ({count} users)'),
(NULL, 'admin.broadcast_btn_send', 'en', '✅ Send'),
(NULL, 'admin.broadcast_btn_cancel', 'en', '❌ Cancel'),
(NULL, 'admin.broadcast_done', 'en', '✅ Broadcast complete!\nSent: {success}/{total}'),
(NULL, 'admin.broadcast_cancelled', 'en', '❌ Broadcast cancelled.'),
(NULL, 'admin.btn_back_admin', 'en', '↩️ Back to admin panel'),

-- Admin Notifications
(NULL, 'notify.channel_no_permission', 'en', '⚠️ Bot does not have admin permissions in channel {channel}. Subscription check skipped.'),
(NULL, 'notify.bot_started', 'en', 'ℹ️ Bot started (bot_id={id})'),
(NULL, 'notify.bot_stopped', 'en', 'ℹ️ Bot stopped'),
(NULL, 'notify.db_error', 'en', '🚨 Database connection error: {error}'),
(NULL, 'notify.unhandled_error', 'en', '🚨 Unhandled error: {error}'),

-- Common
(NULL, 'common.btn_back', 'en', '↩️ Back'),
(NULL, 'common.error', 'en', 'An error occurred. Please try again later.'),
(NULL, 'common.loading', 'en', 'Loading...');
