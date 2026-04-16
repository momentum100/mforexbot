# Bot Flow Documentation

## Screen 1: Language Selection (`/start`)

**Trigger:** `/start` command

**Flow rule:**
- **New user** (not yet in `users` table) → always show language picker first, regardless of Telegram client language. After they pick a language, proceed to Screen 1a.
- **Returning user** → skip picker, use stored `lang_code`, proceed to Screen 1a / Screen 2.
- User can always change language later via "🌐 Change language" button.

**Message text:** "Select language"
**Layout:** Inline keyboard, 2 columns + 1 centered at bottom

| Button | Label | Flag |
|--------|-------|------|
| 1 | English | 🇬🇧 |
| 2 | Русский | 🇷🇺 |
| 3 | Español | 🇪🇸 |
| 4 | العربية | 🇸🇦 |
| 5 | Português | 🇧🇷 |
| 6 | Türkçe | 🇹🇷 |
| 7 | हिन्दी | 🇮🇳 |
| 8 | O'zbek | 🇺🇿 |
| 9 | Azerbaycan | 🇦🇿 |
| 10 | Тоҷикӣ | 🇹🇯 |
| 11 | 한국어 | 🇰🇷 (centered, alone in last row) |

**Grid layout:**
```
[ 🇬🇧 English    ] [ 🇷🇺 Русский   ]
[ 🇪🇸 Español    ] [ 🇸🇦 العربية    ]
[ 🇧🇷 Português  ] [ 🇹🇷 Türkçe    ]
[ 🇮🇳 हिन्दी       ] [ 🇺🇿 O'zbek    ]
[ 🇦🇿 Azerbaycan ] [ 🇹🇯 Тоҷикӣ    ]
[       🇰🇷 한국어       ]
```

**Supported languages (11):** EN, RU, ES, AR, PT, TR, HI, UZ, AZ, TG, KO

**Next:** User taps a language → Screen 1a (channel check) or Screen 2 (main menu if no linked_channel)

---

## Bot Commands Menu (Telegram Menu Button)

Registered via BotFather or `set_my_commands` API. Appears when user clicks "Menu" button in chat.

| Command | Description |
|---------|-------------|
| `/start` | Start the bot |
| `/language` | Change language |
| `/support` | Contact support |
| `/signal` | Get trading signal |

**Note:** Set commands via aiogram `set_my_commands` on bot startup. Admin commands are NOT listed here (hidden from regular users).

---

## Screen 1a: Channel Subscription Check

**Trigger:** After language selection (or immediately on `/start` for returning users), if channel is configured.

**Two fields (both used together):**
- `bots.linked_channel_id` — numeric Telegram chat_id (e.g. `-1002072720405`), used for the `getChatMember` API call. Required for private channels/supergroups without a public username.
- `bots.linked_channel` — URL or `@username` (e.g. `@Jane_Amore_Trading` or `https://t.me/+invite`), used to build the "Subscribe" button URL. Numeric IDs are not valid as URLs.

**Check:** `getChatMember(chat_id=linked_channel_id or linked_channel, user_id=telegram_id)`
- Status `member`, `administrator`, `creator` → pass → Screen 2
- Status `left`, `kicked`, or not found → show gate screen

**Message text:**
```
⚠️ Для использования бота необходимо подписаться на канал:
```

**Layout:**
```
[ 📢 Подписаться на канал ]   ← URL button to channel
[ ✅ Я подписался          ]   ← re-checks membership
```

**Flow:**
1. User clicks "Подписаться" → opens channel in Telegram
2. User subscribes, comes back, clicks "Я подписался"
3. Bot re-checks `getChatMember` → pass or show error

**Requirements:**
- Only checked if `bots.linked_channel_id` (or `linked_channel` as fallback) is NOT NULL
- Bot must be **admin** in the linked channel (read member list permission)
- Uses `getChatMember` API — no need to fetch full user list, checks one user at a time
- If bot lacks permissions in channel → **block user with error message**, notify admins via `AdminNotifier` to fix permissions. Do NOT silently skip the gate.

---

## Screen 1b: Registration Gate (affiliate)

**Trigger:** After the channel gate passes, if `bots.referral_url_template` is set.

**Check:** A `reg` postback must have been received for this user.

```sql
SELECT 1 FROM postback_events
 WHERE bot_id = ? AND telegram_id = ? AND event_type = 'reg'
 LIMIT 1
```

**Referral URL substitution:** the template in `bots.referral_url_template` is rendered with:
- `{user_id}` → Telegram ID (передаётся в партнёрский capture `sub_id1`, откуда возвращается нам в постбеке как `user_id`)
- `{bot_id}` → our bot id (передаётся в партнёрский capture `site_id`, откуда возвращается нам в постбеке как `bot_id`)

Канонический формат отправки — `sub_id1={user_id}&site_id={bot_id}`; см. [postbacks.md](postbacks.md) для полной таблицы соответствия макросов.

Example template saved in DB:
```
https://po-ru4.click/smart/yUKNXc1vSpyXzn?sub_id1={user_id}&site_id={bot_id}
```

**Gate UI (affiliate only — `access_password` is NULL):**
```
⚠️ Для использования бота, пожалуйста, зарегистрируйтесь на партнёрской платформе:
[ 🔗 Зарегистрироваться ]   ← URL button → rendered referral link
[ ✅ Я зарегистрировался ]   ← callback `check_registration`
```

**Gate UI (combined — when BOTH `referral_url_template` AND `access_password` are set):**

Both options are offered in parallel on the same screen — the user picks whichever is easier.

```
⚠️ Для использования бота зарегистрируйтесь по ссылке партнёра
   или введите код доступа:

[ 🔗 Зарегистрироваться ]        ← URL button → rendered referral link
[ ✅ Я зарегистрировался ]        ← callback `check_registration`
─────────────────────────────
[ 🔑 Ввести код доступа ]         ← callback `enter_password` → FSM (Screen 1c flow)
```

The prompt text is `register.combined_required`; the extra button label is `register.btn_enter_password`. Tapping "Ввести код доступа" enters the same FSM state described in Screen 1c (`PasswordGate.waiting_for_password`) and re-uses its cancel/mismatch/success behaviour.

**Gate passes if EITHER a `reg` postback has arrived for this user OR the password was entered correctly** (`users.password_passed = 1`).

**Flow:**
1. User clicks "Register" → affiliate landing opens with click_id + site_id in URL.
2. User completes registration on affiliate side.
3. Affiliate sends postback `bot_id=<bot_id>&user_id=<telegram_id>&event=reg&secret=...` (legacy aliases `site_id` / `click_id` also accepted — см. [postbacks.md](postbacks.md)) → our `/postback` inserts a row into `postback_events`.
4. User clicks "I registered" → bot re-runs the SELECT above. If row found → main menu. Else alert: "⏳ We haven't received your registration yet."
5. (Combined gate only) User may instead tap "🔑 Ввести код доступа" at any time → FSM password-entry flow (see Screen 1c). On success → main menu.

**Notes:**
- If `referral_url_template` is NULL → affiliate gate is skipped.
- Admin users go through the gate too — simplicity over special-casing.
- See [postbacks.md](postbacks.md) for the `/postback` endpoint contract.
- If `bots.access_password` is ALSO set, this screen becomes the **combined gate** above (not a standalone affiliate gate).

### Уведомление о регистрации

При получении постбека `event=reg` (auth_status=ok), PHP автоматически отправляет пользователю сообщение в Telegram с поздравлением и ссылкой на поддержку для получения промо-кода. Текст локализован по `lang_code` пользователя. Ключ перевода: `postback.reg_congrats`. Сообщение содержит плейсхолдер `{support_link}`, подставляемый из `bots.support_link`. Отправка — fire-and-forget: ошибки логируются, но не влияют на ответ постбека.

---

## Screen 1c: Password Gate

**Trigger:** After the channel gate passes, when `bots.access_password` is set (NOT NULL and non-empty). Two shapes:

- **Standalone password gate** — `access_password` is set, `referral_url_template` is NULL. This screen is shown on its own.
- **Password-half of the combined gate** — both fields are set. The same FSM flow documented below is reached by tapping the "🔑 Ввести код доступа" button on the Screen 1b combined gate.

**Check:** `users.password_passed = 1` for the current user.
- `1` → bypass gate → Screen 2.
- `0` → show password gate (standalone) or combined gate (Screen 1b).

**Gate UI (standalone — initial message):**
```
🔒 Введите пароль доступа:
[ ↩️ Отмена ]
```

The bot enters an FSM state (`PasswordGate.waiting_for_password`) and waits for the next plain text message from the user. In the combined-gate case, the same FSM state is entered when the user taps "🔑 Ввести код доступа".

**Flow:**
1. Bot shows `password.required` ("Введите код доступа:" / "Enter access code:") with a single inline `↩️ Отмена` / `↩️ Cancel` button (callback `password_cancel`).
2. User replies with a plain text message.
3. Bot compares `message.text.strip()` to `bots.access_password` as plaintext (direct string equality).
   - **Match** → `UPDATE users SET password_passed = 1 WHERE id = ?` → clear FSM state → proceed to Screen 2 (Main Menu). Optionally delete the password message from chat for hygiene.
   - **Mismatch** → show alert / ephemeral message `password.wrong` ("❌ Неверный код. Попробуйте ещё раз." / "❌ Wrong code. Try again.") → remain in FSM state (user can try again without re-issuing `/start`).
4. If user taps `↩️ Отмена` → clear FSM state, show `password.cancelled` ("Отменено." / "Cancelled."), no main menu is shown.

**Mismatch response (ephemeral / alert):**
```
❌ Неверный код. Попробуйте ещё раз.
```

**Cancel response:**
```
Отменено.
```

**Notes:**
- Password is plaintext: stored in `bots.access_password` as-is, compared as-is, and the admin reads/edits it directly in the admin panel.
- Returning users with `users.password_passed = 1` **bypass the gate entirely** — no re-prompt on subsequent `/start`.
- Admin users go through the gate too (same rationale as Screen 1b — simplicity over special-casing).
- Recommended: delete the user's message containing the password after evaluation, so the code does not linger in chat history.

### Gate selection logic

Decision table applied *after* the channel-subscription check (Screen 1a) passes:

| `bots.access_password` | `bots.referral_url_template` | Active gate |
|------------------------|------------------------------|-------------|
| NULL / empty           | NULL / empty                 | **No gate** — straight to Screen 2 |
| NULL / empty           | set                          | **Screen 1b (affiliate-only)** — postback gate |
| set                    | NULL / empty                 | **Screen 1c (standalone)** — password gate |
| set                    | set                          | **Combined gate** — Screen 1b layout with both options shown on one screen (register via partner OR enter password) |

Both options are offered in parallel — the user picks whichever is easier. The gate passes if EITHER a `reg` postback arrived OR the password matched.

**Resolution flow (pseudo):**
```
if not channel_passed: show 1a
has_pw   = bool(bots.access_password)
has_ref  = bool(bots.referral_url_template)
passed   = users.password_passed or has_reg_postback(user)

if not has_pw and not has_ref:    show 2
elif passed:                      show 2
elif has_ref and has_pw:          show 1b (combined layout)
elif has_ref:                     show 1b (affiliate only)
else:                             show 1c (standalone password)
```

---

## Screen 2: Main Menu

**Trigger:** Language selected from Screen 1
**Image:** Banner image with text "ГЛАВНОЕ МЕНЮ" (Main Menu) — dark/red themed with money graphics
**Message text:** "Главное меню:" (localized)
**Layout:** Inline keyboard, 2 columns top row + 1 centered + 1 full-width bottom

```
[ 📚 Инструкция      ] [ 🌐 Выбрать язык    ]
[       🔗 Поддержка        ]
[    📊 Получить сигнал     ]
```

| # | Button | Translation | Notes |
|---|--------|-------------|-------|
| 1 | 📚 Инструкция | Instructions | |
| 2 | 🌐 Выбрать язык | Select language | → Back to Screen 1 |
| 3 | 🔗 Поддержка | Support | URL button → `bots.support_link` |
| 4 | 📊 Получить сигнал | Get signal | |

**Admin-only button (visible when `is_admin = 1`):**
```
[       ⚙️ Админ-панель       ]
```
→ Screen 5: Admin Menu

**Next:** TBD per button

---

## Screen 3: Instructions (Инструкция)

**Trigger:** "📚 Инструкция" button from Main Menu
**Image:** Banner with text "ИНСТРУКЦИЯ" (Instructions) — dark/red themed
**Message text (localized, RU shown):**

> 🤖 Бот основан и обучен на кластерной нейронной сети OpenTrandAI!
>
> 👨‍💻 Для обучения бота было проанализировано более 3 миллионов трейдовых событий. В настоящее время пользователи бота успешно генерируют 5-25% от своего капитала ежедневно!
>
> Бот всё ещё в процессе обучения, доработки, несмотря на это точность анализа находится на достаточно высоком уровне! Чтобы достичь максимальную прибыль, следуйте этой инструкции:
>
> 1️⃣ Зарегистрируйтесь на сайте Pocket Option
> 2️⃣ Пополните баланс своего счёта.
> 3️⃣ Перейдите в раздел реальной торговли.
> 4️⃣ Выберите валютную пару на сайте, а так же время экспирации
> 5️⃣ Загрузите анализ в бота и торгуйте следуя его анализу.
> ⚠️ В случае неудачного сигнала рекомендуем удвоить сумму (максимум 3 раза подряд, в случае неудачи, переждать не входя валютную пару), тобы достичь прибыль с помощью следующего сигнала.
>
> ⛔ Без регистрации и промокода доступ к сигналам не будет открыт

**Layout:** Single button, full-width

```
[       ↩️ Вернуться в главное меню       ]
```

| # | Button | Translation | Action |
|---|--------|-------------|--------|
| 1 | ↩️ Вернуться в главное меню | Back to main menu | → Screen 2 |

**Notes:**
- "Pocket Option" in step 1 may be a clickable inline link (URL in message text)
- Instruction text is localized per selected language
- Contains step-by-step trading guide (6 steps + warning)

---

## Screen 4: Get Signal — Web App (Получить сигнал)

**Trigger:** "📊 Получить сигнал" button from Main Menu
**Type:** Telegram Mini Web App (not inline keyboard)
**Title bar:** "Analyzing Trading"

### Section 1: Signal Configuration

**Header:** "Торговые Сигналы" (Trading Signals)
**Subtitle:** "Профессиональные сигналы для бинарных опционов" (Professional signals for binary options)

#### Tabs (top toggle)
```
[ + Форекс (selected) ] [ ☆ OTC ]
```

#### Signal Form

**Language selector:** Globe icon + flag dropdown (🌐 🇷🇺 ▼)

**ВАЛЮТНАЯ ПАРА (Currency Pair):** Searchable dropdown ("Start typing, e.g. EUR/USD")

Currency pairs (25 total):
1. AUD/CAD
2. AUD/CHF
3. AUD/JPY
4. AUD/USD
5. CAD/CHF
6. CAD/JPY
7. CHF/JPY
8. EUR/AUD
9. EUR/CAD
10. EUR/CHF
11. EUR/GBP
12. EUR/JPY
13. EUR/USD
14. GBP/CAD
15. GBP/CHF
16. GBP/JPY
17. GBP/USD
18. NZD/JPY
19. NZD/USD
20. USD/CAD
21. USD/CHF
22. USD/JPY

**ВРЕМЯ ЭКСПИРАЦИИ (Expiration Time):** Button grid, 2 columns + 1

```
[ 1m  (selected) ] [ 3m            ]
[ 5m             ] [ 15m           ]
[ 30m            ]
```

#### OTC Tab
Same form layout as Forex tab. Currency pairs prefixed with "OTC" label.

Visible OTC pairs (partial list from screenshot):
1. OTC NZD/USD
2. OTC TND/USD
3. OTC UAH/USD
4. OTC USD/ARS
5. OTC USD/BRL
6. OTC USD/CAD
7. OTC USD/CHF

**Note:** Full OTC pair list TBD — need more screenshots or full scroll. Same searchable dropdown as Forex tab.

---

| Value | Label |
|-------|-------|
| 1m | 1 minute |
| 3m | 3 minutes |
| 5m | 5 minutes |
| 15m | 15 minutes |
| 30m | 30 minutes |

**Action button (full-width, gradient blue-green):**
```
[ ⚡ Получить сигнал ]
```

---

### Section 2: Signal Result (after clicking "Получить сигнал")

**UX: Hidden by default. On signal generation, slide down with smooth animation (CSS transition/animation). Do NOT show empty placeholder state.**

**Card: "Сигнал сгенерирован!" (Signal generated!)**

| Field | Translation | Values | Color |
|-------|-------------|--------|-------|
| Expiration | — | 5s, 1m, 3m, 5m, 15m, 30m | white |
| НАПРАВЛЕНИЕ СИГНАЛА | Signal direction | **Покупать** (Buy) / **Продавать** (Sell) | Green / Red |
| УРОВЕНЬ УВЕРЕННОСТИ | Confidence level | **Низкий** (Low) / **Средний** (Medium) / **Высокий** (High) | Orange / Yellow / Green (TBD) |
| Timestamp | — | e.g. 12:55:26 PM | white |

**Signal direction values:**
- Покупать (Buy) — displayed in **green**
- Продавать (Sell) — displayed in **red**

**Confidence level values:**
- Низкий (Low) — displayed in **orange**
- Средний (Medium) — TBD color
- Высокий (High) — TBD color

---

### Section 3: Market Chart

**Header:** "График рынка" (Market chart)
**Subtitle:** "График от TradingView" (Chart from TradingView)

- Embedded TradingView widget
- Shows: search bar, zoom (🔍 ⊕), timeframes (1м, 30м, 1ч)
- Chart displays: candlesticks, MA 9, RSI 14, Volume
- Example shown: EURUSD

**Notes:**
- This is a **Telegram Mini Web App** (opened via `web_app` button type)
- Two main tabs: Форекс (Forex) and OTC
- Language persists from bot selection
- TradingView chart is embedded widget — may need TradingView widget API
- Signal direction and confidence are generated server-side
- **Styling:** Tailwind CSS + shadcn/ui components (dark theme matching Telegram Mini App aesthetic)
- **All texts are localized** — fetched from DB by key + user language, never hardcoded
- **Access gate:** On load, the webapp calls `GET /app/{bot_id}/api/check-access?tg_id=TELEGRAM_ID` to verify `users.status`. If status is `registered` or `deposited`, the signal form is shown. Otherwise, a localized stub page is displayed with instructions to complete bot gates and a link to support. The "Check again" button re-calls the endpoint without reloading. Status lifecycle: `new` (default) -> `registered` (after passing all bot gates or `reg` postback) -> `deposited` (after `ftd` postback). Status only upgrades, never downgrades.

---

## Screen 5: Admin Menu (Админ-панель)

**Trigger:** "⚙️ Админ-панель" button from Main Menu (admin only)
**Visible only to:** users with `is_admin = 1`
**Message text:** "Админ-панель:"

**Layout:** Inline keyboard, single column

```
[ 📊 Рассылка           ]
[ 📈 Статистика          ]
[ 🔗 Смена реферальной ссылки ]
[ 📋 Промокоды           ]
[ ↩️ Грач по Пыбинскому  ]
```

| # | Button | Translation | Action |
|---|--------|-------------|--------|
| 1 | 📊 Рассылка | Broadcast | Send message to all users |
| 2 | 📈 Статистика | Statistics | View bot stats |
| 3 | 🔗 Смена реферальной ссылки | Change referral link | Update referral URL |
| 4 | 📋 Промокоды | Promo codes | Manage promo codes |
| 5 | ↩️ Грач по Пыбинскому | Back (?) | → Screen 2: Main Menu |

**Notes:**
- Button 5 text unclear from screenshot — confirm exact label
- Non-admin users never see the admin button in main menu

**Next:** Per button screens below

---

## Screen 5a: Statistics (Статистика)

**Trigger:** "📈 Статистика" from Admin Menu
**Message text (dynamic):**

```
📊 Статистика бота

👥 Всего пользователей: {total}
✅ Прошли регистрацию: {registered}
💰 Сделали первый депозит: {deposited}
```

**Layout:** Single button
```
[ ↩️ Вернуться в админ-панель ]
```

**Data source:**
- `SELECT COUNT(*) FROM users WHERE bot_id = ?` → total
- `SELECT COUNT(*) FROM users WHERE bot_id = ? AND status IN ('registered', 'deposited')` → registered
- `SELECT COUNT(*) FROM users WHERE bot_id = ? AND status = 'deposited'` → deposited

---

## Screen 5b: Broadcast (Рассылка)

**Trigger:** "📊 Рассылка" from Admin Menu

### Flow:
1. Bot sends: "Перешлите или отправьте сообщение для рассылки:" (Forward or send a message for broadcast)
2. Admin forwards a message to the bot (supports text, images, video, documents — any message type)
3. Bot asks target audience:
   ```
   📢 Кому отправить рассылку?
   ```
   ```
   [ 👥 Всем                  ]
   [ ✅ Прошли регистрацию     ]
   [ 💸 Сделали депозит        ]
   [ ❌ Отмена                 ]
   ```
4. Admin selects audience → bot shows confirmation with count:
   ```
   📨 Сообщение для рассылки получено.
   Отправить? ({user_count} чел.)
   ```
   ```
   [ ✅ Отправить ] [ ❌ Отмена ]
   ```
5. On confirm → bot copies the forwarded message (`copy_message`) to filtered users
6. Shows result:
   ```
   ✅ Рассылка завершена!
   Отправлено: {success_count}/{total_count}
   ```

**Implementation notes:**
- Use `message.forward_from_message_id` or just store the incoming message's `chat_id` + `message_id`
- Use `copy_message` API (not `forward_message`) to send clean copies without "Forwarded from" header
- Admin can forward from Saved Messages to preserve formatting, images, etc.
- Process in batches to respect Telegram rate limits (~30 msgs/sec)
- Track success/fail count

**Layout after completion:**
```
[ ↩️ Вернуться в админ-панель ]
```

---

## Screen 5c: Reminders (Напоминания) — STUB / ON HOLD

**Trigger:** TBD — likely an admin menu button (not yet visible in screenshots)
**Purpose:** Scheduled/one-time automated messages to user segments
**Status:** On hold — show menu placeholder, implementation later

**Message text (dynamic):**
```
🔔 Напоминания (по расписанию, однократно)

📬 Получили за последние 24 часа
👥 Всего: {total}
🚫 Без регистрации: {no_reg}
💳 Без депозита (но с рег.): {reg_no_dep}
💰 С депозитом: {deposited}

/rem_diag - диагностика напоминаний
```

**Layout:** Inline keyboard, single column
```
[ 👥 Всем — настроить                    ]
[ 🚫 Без регистрации — настроить          ]
[ 💳 Без депозита (с рег.) — настроить    ]
[ 💰 С депозитом — настроить              ]
[ 👈 Назад                                ]
```

**Segments mapped to user status:**
| Button | Filter |
|--------|--------|
| Всем | All users |
| Без регистрации | `status = 'new'` |
| Без депозита (с рег.) | `status = 'registered'` |
| С депозитом | `status = 'deposited'` |

**Notes:**
- Each segment has its own reminder config (message, schedule, enabled/disabled)
- "По расписанию, однократно" = can be scheduled recurring or one-time
- `/rem_diag` command for diagnostics — TBD what it shows
- 24h stats show how many users actually received reminders
- Full sub-flow for configuring each reminder TBD — need more screenshots

---

## Database Schema

### DB Table: `bots`

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | PK (`bot_id`) |
| name | VARCHAR(255) | Bot display name |
| token | VARCHAR(255) | Telegram Bot API token |
| webapp_url | VARCHAR(255) NULL | Base URL for Mini Web App |
| linked_channel | VARCHAR(255) NULL | Channel URL or @username — used to build the "Subscribe" button URL |
| linked_channel_id | VARCHAR(50) NULL | Numeric Telegram chat_id (e.g. `-1001234567890`) — used for `getChatMember` API calls |
| support_link | VARCHAR(255) NULL | Support contact t.me/ link (URL button) |
| referral_url_template | VARCHAR(500) NULL | Referral URL with `{user_id}` placeholder |
| postback_secret | VARCHAR(255) NULL | Shared secret for postback authentication |
| access_password | VARCHAR(64) NULL | If set, enables the **password gate**. Plaintext bot-wide shared code, managed via admin panel (read/edit as-is). If `referral_url_template` is also set, the gate becomes a **combined screen** (Screen 1b) offering both options in parallel. See Screen 1c. |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### DB Table: `users`

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| bot_id | INT | FK → bots.id |
| telegram_id | BIGINT | Telegram user ID |
| username | VARCHAR(255) | Telegram @username |
| first_name | VARCHAR(255) | Telegram first name |
| last_name | VARCHAR(255) | Telegram last name |
| bio | TEXT | Telegram bio |
| lang_code | VARCHAR(5) | Selected language (default: en) |
| is_admin | TINYINT(1) | 0 = regular, 1 = bot admin (sees admin menu in bot) |
| status | ENUM('new','registered','deposited') | User progression: new → registered → deposited |
| password_passed | TINYINT(1) | `1` once the user has successfully entered `bots.access_password` on this bot (Screen 1c). Defaults to `0`. Ignored when `bots.access_password` is NULL. |
| created_at | DATETIME | |
| updated_at | DATETIME | |

**Unique constraint:** (`bot_id`, `telegram_id`)

### DB Table: `translations`

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| bot_id | INT NULL | FK → bots.id (NULL = base/shared default) |
| `key` | VARCHAR(255) | Translation key (e.g. `main_menu.title`) |
| lang_code | VARCHAR(5) | Language code |
| value | TEXT | Translated text |

**Unique constraint:** (`bot_id`, `key`, `lang_code`)

**Two-tier resolution:**
1. Bot override: `WHERE key = ? AND lang_code = ? AND bot_id = X`
2. Base default: `WHERE key = ? AND lang_code = ? AND bot_id IS NULL`
3. English fallback: `WHERE key = ? AND lang_code = 'en' AND bot_id IS NULL`

### DB Table: `admin_users` (web admin panel — separate from Telegram users)

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| username | VARCHAR(255) | Login username (unique) |
| password | VARCHAR(255) | Bcrypt hashed password |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### DB Table: `languages`

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| code | VARCHAR(5) | Language code (unique) |
| name | VARCHAR(50) | English name |
| native_name | VARCHAR(50) | Native display name |
| flag | VARCHAR(10) | Flag emoji |
| sort_order | INT | Display order in language selector |

### DB Table: `currency_pairs`

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| bot_id | INT NULL | FK → bots.id (NULL = shared across all bots) |
| symbol | VARCHAR(20) | e.g. EUR/USD, OTC NZD/USD |
| type | ENUM('forex','otc') | Pair type |
| is_active | TINYINT(1) | Enable/disable pair |
| sort_order | INT | Display order |

### DB Table: `promo_codes`

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| bot_id | INT | FK → bots.id |
| code | VARCHAR(50) | Promo code string |
| is_active | TINYINT(1) | Enable/disable |
| max_uses | INT NULL | NULL = unlimited |
| current_uses | INT | Counter |

### Key naming convention
- Dot-separated, scoped by screen/feature
- Examples: `main_menu.title`, `main_menu.btn_instruction`, `signal.buy`, `webapp.currency_pair_placeholder`

### Translation keys for the password / combined gate (Screen 1b + 1c)

All keys must be seeded for every supported language (EN, RU, ES, AR, PT, TR, HI, UZ, AZ, TG, KO) as base translations (`bot_id = NULL`). Per-bot overrides remain optional. Seeded by `migrations/016_seed_password_translations.sql`.

| Key | Purpose | RU example | EN example |
|-----|---------|------------|------------|
| `password.required` | Initial prompt shown when standalone password gate is hit, or when the user has tapped "Enter access code" on the combined gate | `Введите код доступа:` | `Enter access code:` |
| `password.wrong` | Alert / ephemeral reply on mismatch | `❌ Неверный код. Попробуйте ещё раз.` | `❌ Wrong code. Try again.` |
| `password.btn_cancel` | Cancel inline-button label | `↩️ Отмена` | `↩️ Cancel` |
| `password.cancelled` | Confirmation shown after Cancel | `Отменено.` | `Cancelled.` |
| `register.btn_enter_password` | Extra button on the combined gate that opens the password FSM | `🔑 Ввести код доступа` | `🔑 Enter access code` |
| `register.combined_required` | Prompt text on the combined gate (affiliate + password on one screen) | `⚠️ Для использования бота зарегистрируйтесь по ссылке партнёра или введите код доступа:` | `⚠️ To use the bot, register via the partner link or enter an access code:` |

### Multi-tenant note
- **Every query** filters by `bot_id`
- Python bot process: `python main.py --bot-id=1`
- Web App URL: `/app/{bot_id}/`
- Admin panel manages all bots from one interface
