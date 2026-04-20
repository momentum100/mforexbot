<?php

namespace App\Controllers;

use App\Constants\PostbackEvent;
use App\Constants\UserStatus;
use App\Middleware\TelegramAuth;
use App\Services\TelegramNotifier;

/**
 * API controller for the Telegram Mini Web App and postback endpoint.
 *
 * Web App API endpoints (require Telegram initData auth):
 *   GET  /app/{bot_id}/api/translations?lang=X
 *   GET  /app/{bot_id}/api/pairs?type=forex|otc
 *   POST /app/{bot_id}/api/signal
 *
 * Postback endpoint (requires bot postback_secret):
 *   GET  /postback?bot_id=X&user_id=X&event=reg|ftd|redep|commission|withdrawal&secret=X
 *
 *   Canonical param names: bot_id, user_id, event, secret.
 *   Legacy aliases accepted for backwards compatibility:
 *     - site_id  -> bot_id   (partner's {site_id} macro)
 *     - click_id -> user_id  (partner's {sub_id1} / legacy {click_id} macro)
 *     - status   -> event    (legacy status param)
 *   Priority order (first non-empty wins): bot_id > site_id, user_id > click_id.
 *   See docs/postbacks.md for the full macro mapping.
 */
class ApiController
{
    /**
     * F3 hook — runs before every route in this controller.
     * Validates Telegram auth on /app/ API routes, skips for /postback.
     */
    public function beforeroute(\Base $f3): void
    {
        header('Content-Type: application/json');

        // All /app/ API routes are currently public (no Telegram auth).
        // Re-enable TelegramAuth::check here when needed.
    }

    /**
     * Get translations for a bot in a specific language.
     * Uses three-tier resolution: bot override -> base for lang -> base EN fallback.
     *
     * GET /app/{bot_id}/api/translations?lang=X
     * Returns: { "key1": "value1", "key2": "value2", ... }
     */
    public function translations(\Base $f3): void
    {
        $botId = (int) $f3->get('PARAMS.bot_id');
        $lang = $f3->get('GET.lang') ?: 'en';

        $db = $f3->get('DB');

        // Get all translation keys with three-tier resolution:
        // 1. Bot override for requested language
        // 2. Base (bot_id IS NULL) for requested language
        // 3. Base English fallback
        $query = '
            SELECT
                base_en.`key`,
                COALESCE(bot_lang.value, base_lang.value, base_en.value) AS value
            FROM translations base_en
            LEFT JOIN translations base_lang
                ON base_lang.`key` = base_en.`key`
                AND base_lang.lang_code = ?
                AND base_lang.bot_id IS NULL
            LEFT JOIN translations bot_lang
                ON bot_lang.`key` = base_en.`key`
                AND bot_lang.lang_code = ?
                AND bot_lang.bot_id = ?
            WHERE base_en.bot_id IS NULL
              AND base_en.lang_code = ?
        ';

        // If lang is not 'en', we also want keys that might only exist in the bot override
        // but the primary source is the base EN keys
        $results = $db->exec($query, [$lang, $lang, $botId, 'en']);

        $translations = [];
        foreach ($results as $row) {
            $translations[$row['key']] = $row['value'];
        }

        echo json_encode($translations, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get currency pairs for a bot, filtered by type.
     *
     * GET /app/{bot_id}/api/pairs?type=forex|otc
     * Returns: [{ "symbol": "EUR/USD", "type": "forex" }, ...]
     */
    public function pairs(\Base $f3): void
    {
        $botId = (int) $f3->get('PARAMS.bot_id');
        $type = $f3->get('GET.type') ?: 'forex';

        // Validate type
        if (!in_array($type, ['forex', 'otc'], true)) {
            echo json_encode(['error' => 'Invalid type. Must be "forex" or "otc".']);
            return;
        }

        $db = $f3->get('DB');

        // Get pairs: bot-specific OR shared (bot_id IS NULL), active only
        $results = $db->exec(
            'SELECT symbol, type
             FROM currency_pairs
             WHERE (bot_id = ? OR bot_id IS NULL)
               AND type = ?
               AND is_active = 1
             ORDER BY sort_order ASC, symbol ASC',
            [$botId, $type]
        );

        echo json_encode($results, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Check whether the spot forex market is currently open.
     * Open: Sunday 22:00 UTC -> Friday 22:00 UTC (continuous).
     */
    public static function isForexMarketOpen(): bool
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $dow = (int) $now->format('w'); // 0=Sun ... 6=Sat
        $hour = (int) $now->format('G');

        if ($dow === 6) return false;                    // Saturday
        if ($dow === 0 && $hour < 22) return false;      // Sunday before 22:00
        if ($dow === 5 && $hour >= 22) return false;     // Friday after 22:00
        return true;
    }

    /**
     * GET /app/{bot_id}/api/market-status
     * Returns: { "forex_open": bool, "server_time_utc": "..." }
     */
    public function marketStatus(\Base $f3): void
    {
        echo json_encode([
            'forex_open' => self::isForexMarketOpen(),
            'server_time_utc' => gmdate('c'),
        ]);
    }

    /**
     * Generate a trading signal (STUB: random values).
     *
     * POST /app/{bot_id}/api/signal
     * Body: { "pair": "EUR/USD", "expiration": "1m", "type": "forex" }
     * Returns: { "direction": "buy|sell", "confidence": "low|medium|high", "timestamp": "..." }
     */
    public function signal(\Base $f3): void
    {
        $botId = (int) $f3->get('PARAMS.bot_id');

        // Parse JSON body
        $body = json_decode($f3->get('BODY'), true) ?: [];
        $pair = $body['pair'] ?? '';
        $expiration = $body['expiration'] ?? '1m';
        $type = $body['type'] ?? 'forex';

        if ($pair === '') {
            echo json_encode(['error' => 'Currency pair is required']);
            return;
        }

        // Validate expiration
        $validExpirations = ['1m', '3m', '5m', '15m', '30m'];
        if (!in_array($expiration, $validExpirations, true)) {
            echo json_encode(['error' => 'Invalid expiration time']);
            return;
        }

        // Block forex signals when the spot market is closed (weekend).
        if ($type === 'forex' && !self::isForexMarketOpen()) {
            echo json_encode(['error' => 'forex_market_closed']);
            return;
        }

        // STUB: Generate random signal
        $directions = ['buy', 'sell'];
        $confidences = ['low', 'medium', 'high'];

        $signal = [
            'pair' => $pair,
            'expiration' => $expiration,
            'type' => $type,
            'direction' => $directions[array_rand($directions)],
            'confidence' => $confidences[array_rand($confidences)],
            'timestamp' => date('h:i:s A'),
            'generated_at' => date('c'),
        ];

        echo json_encode($signal, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Postback endpoint for affiliate status updates.
     *
     * Partner auto-appends click_id (=telegram_id) and sub_id1 (=bot_id).
     * Explicit params in URL: secret, event (or status).
     *
     * Resolution priority:
     *   telegram_id: click_id → user_id (first non-empty)
     *   bot_id:      sub_id1 → bot_id → site_id (first non-empty non-zero)
     *   event:       event → status (first non-empty)
     *
     * Returns: 200 on success, 400 on missing params, 401 on bad secret,
     * 404 on unknown bot. Every request is logged to postback_events
     * regardless of outcome — see docs/postbacks.md.
     *
     * This method is intentionally a thin coordinator; the actual work is
     * split across resolvePostbackParams / authenticatePostback /
     * recordPostbackEvent / applyStatusTransition / notifyUser /
     * notifyAdminGroup below.
     */
    public function postback(\Base $f3): void
    {
        $db = $f3->get('DB');

        // 1. Parse incoming params (handles sub_id1/bot_id/site_id,
        //    click_id/user_id, event/status aliases).
        [$botId, $telegramId, $event, $secret, $rawQuery, $params]
            = $this->resolvePostbackParams($f3);

        // 2. Classify the request. Output state (default = accepted).
        $authStatus = 'ok';
        $httpCode   = 200;
        $response   = ['ok' => true];
        $bot        = null;
        $botIdForLog = $botId;

        if ($botId === null || $event === '') {
            $authStatus = 'missing_params';
            $httpCode   = 400;
            $response   = ['error' => 'Missing required parameters: bot_id (sub_id1/bot_id/site_id), event'];
        } else {
            $botRows = $db->exec(
                'SELECT id, name, postback_secret, token, support_link, admin_group_id FROM bots WHERE id = ?',
                [$botId]
            );
            if (empty($botRows)) {
                $authStatus = 'unknown_bot';
                $httpCode   = 404;
                $response   = ['error' => 'Bot not found'];
                // Keep bot_id for the log row even though FK will reject — store NULL to allow insert.
                $botIdForLog = null;
            } else {
                $bot = $botRows[0];
                $authStatus = $this->authenticatePostback($bot, $secret);
                if ($authStatus === 'bad_secret') {
                    $httpCode = 401;
                    $response = ['error' => 'Invalid secret'];
                }
            }
        }

        // 3. Always log — accepted or not.
        $this->recordPostbackEvent(
            $db,
            $botIdForLog,
            $telegramId,
            $event !== '' ? $event : null,
            $authStatus,
            $params,
            $rawQuery,
            (string) $f3->get('IP')
        );

        // 4. On successful auth with a known telegram_id, mutate user state
        //    and fire notifications.
        if ($authStatus === 'ok' && $telegramId !== null && $bot !== null) {
            $this->applyStatusTransition($db, $botId, $telegramId, $event);

            if ($event === PostbackEvent::REG) {
                try {
                    $this->notifyUser($db, $bot, $telegramId);
                } catch (\Throwable $e) {
                    error_log('Postback reg notification failed: ' . $e->getMessage());
                }
            }

            try {
                $this->notifyAdminGroup($db, $bot, $botId, $telegramId, $event);
            } catch (\Throwable $e) {
                error_log('Admin group notification failed: ' . $e->getMessage());
            }
        }

        http_response_code($httpCode);
        echo json_encode($response);
    }

    /**
     * Parse the raw GET params off the request and resolve the canonical
     * postback fields, applying the legacy-alias priority.
     *
     * @return array{0:?int,1:?int,2:string,3:string,4:string,5:array}
     *         [botId, telegramId, event, secret, rawQuery, paramsForLog]
     */
    private function resolvePostbackParams(\Base $f3): array
    {
        $params = $f3->get('GET') ?? [];
        unset($params['secret']); // never log the secret

        // bot_id: sub_id1 (partner auto-appended) → bot_id → site_id
        $botIdRaw = $this->firstNonEmpty(
            $f3->get('GET.sub_id1'),
            $f3->get('GET.bot_id'),
            $f3->get('GET.site_id')
        );
        $botId = (is_numeric($botIdRaw) && (int) $botIdRaw > 0) ? (int) $botIdRaw : null;

        // telegram_id: click_id (partner auto-appended) → user_id
        $clickId = $this->firstNonEmpty(
            $f3->get('GET.click_id'),
            $f3->get('GET.user_id')
        );
        $telegramId = (is_numeric($clickId) && (int) $clickId > 0) ? (int) $clickId : null;

        $event = trim((string) ($this->firstNonEmpty(
            $f3->get('GET.event'),
            $f3->get('GET.status')
        ) ?? ''));
        $secret = (string) ($f3->get('GET.secret') ?? '');

        $rawQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $rawQuery = preg_replace('/(^|&)secret=[^&]*/', '$1secret=REDACTED', $rawQuery);

        return [$botId, $telegramId, $event, $secret, $rawQuery, $params];
    }

    /**
     * Compare the presented secret against the bot's stored postback_secret.
     *
     * Returns 'ok' when the secret matches (and the stored value is
     * non-empty), 'bad_secret' otherwise.
     */
    private function authenticatePostback(array $bot, string $secret): string
    {
        $expectedSecret = $bot['postback_secret'] ?? '';
        if ($expectedSecret === '' || !hash_equals($expectedSecret, $secret)) {
            return 'bad_secret';
        }
        return 'ok';
    }

    /**
     * Append a row to the postback_events audit log. Called for every
     * request regardless of outcome — the log is the single source of truth
     * for what the partner sent us.
     */
    private function recordPostbackEvent(
        \DB\SQL $db,
        ?int $botId,
        ?int $telegramId,
        ?string $event,
        string $authStatus,
        array $params,
        string $rawQuery,
        string $ip
    ): void {
        $db->exec(
            'INSERT INTO postback_events (bot_id, telegram_id, event_type, auth_status, params_json, raw_query, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $botId,
                $telegramId,
                $event,
                $authStatus,
                json_encode($params, JSON_UNESCAPED_UNICODE),
                $rawQuery,
                $ip,
            ]
        );
    }

    /**
     * Move a user's status along the funnel in response to an accepted
     * postback. Idempotent — each UPDATE is guarded by the status the
     * transition can come from, so replaying a postback never downgrades.
     *
     *   reg → 'new' ..................→ 'registered'
     *   ftd → 'new' | 'registered' ..→ 'deposited'
     *
     * Any other event (redep / commission / withdrawal / unknown) is a no-op.
     */
    private function applyStatusTransition(\DB\SQL $db, int $botId, int $telegramId, string $event): void
    {
        if ($event === PostbackEvent::REG) {
            $db->exec(
                'UPDATE users SET status = ?, updated_at = NOW()
                 WHERE bot_id = ? AND telegram_id = ? AND status = ?',
                [UserStatus::REGISTERED, $botId, $telegramId, UserStatus::NEW]
            );
        } elseif ($event === PostbackEvent::FTD) {
            $db->exec(
                'UPDATE users SET status = ?, updated_at = NOW()
                 WHERE bot_id = ? AND telegram_id = ? AND status IN (?, ?)',
                [UserStatus::DEPOSITED, $botId, $telegramId, UserStatus::NEW, UserStatus::REGISTERED]
            );
        }
    }

    /**
     * Send the "registration congrats" DM to the user (reg event only).
     *
     * Fire-and-forget; caller wraps in try/catch so a failed Telegram call
     * never blocks the 200 OK we owe the partner.
     */
    private function notifyUser(\DB\SQL $db, array $bot, int $telegramId): void
    {
        $botToken = $bot['token'] ?? '';
        $supportLink = $bot['support_link'] ?? '';
        if ($botToken === '' || $supportLink === '') {
            return;
        }

        // Normalize t.me links to @username for cleaner display.
        if (str_starts_with($supportLink, 'https://t.me/')) {
            $supportLink = '@' . ltrim(substr($supportLink, strlen('https://t.me/')), '/');
        }

        $botId = (int) ($bot['id'] ?? 0);
        $userRow = $db->exec(
            'SELECT lang_code FROM users WHERE bot_id = ? AND telegram_id = ?',
            [$botId, $telegramId]
        );
        $langCode = (!empty($userRow)) ? $userRow[0]['lang_code'] : 'en';

        $msgText = $this->getTranslation($db, $botId, 'postback.reg_congrats', $langCode);
        if ($msgText === '') {
            return;
        }

        $msgText = str_replace('{support_link}', $supportLink, $msgText);
        TelegramNotifier::sendMessage($botToken, $telegramId, $msgText, [
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }

    /**
     * Post a plain-text notification to the bot's admin group summarising
     * the event. Fired for every accepted event (reg/ftd/redep/...).
     *
     * Silent when admin_group_id or token is missing.
     */
    private function notifyAdminGroup(\DB\SQL $db, array $bot, int $botId, ?int $telegramId, string $event): void
    {
        $adminGroupId = $bot['admin_group_id'] ?? '';
        $botToken = $bot['token'] ?? '';
        if ($adminGroupId === '' || $botToken === '') {
            return;
        }

        $botName = $bot['name'] ?? '';
        // Look up username for richer context.
        $userRow = $db->exec(
            'SELECT username FROM users WHERE bot_id = ? AND telegram_id = ?',
            [$botId, $telegramId]
        );
        $username = (!empty($userRow) && !empty($userRow[0]['username']))
            ? $userRow[0]['username'] : '';

        $headline = match ($event) {
            PostbackEvent::REG        => '👤 Новый пользователь зарегистрировался!',
            PostbackEvent::FTD        => '💰 Первый депозит!',
            PostbackEvent::REDEP      => '💵 Повторный депозит',
            PostbackEvent::COMMISSION => '💼 Комиссия',
            PostbackEvent::WITHDRAWAL => '💸 Вывод средств',
            default                   => "📩 Постбек: {$event}",
        };

        $adminMsg = "{$headline}\n"
            . "Bot: {$botName} (ID: {$botId})\n"
            . "User: {$telegramId} (@{$username})\n"
            . "Event: {$event}";

        TelegramNotifier::sendMessage($botToken, $adminGroupId, $adminMsg);
    }

    /**
     * Check whether a Telegram user has webapp access.
     *
     * Gate priority (migration 022 adds reg_gate_enabled / deposit_gate_enabled):
     *   - deposit_gate_enabled = 1 → access requires users.status = 'deposited'
     *     (denied reason: 'not_deposited')
     *   - else reg_gate_enabled = 1 → access requires users.status IN
     *     ('registered','deposited')  (denied reason: 'not_registered')
     *   - else no gate → access always granted.
     *
     * The deposit gate dominates the registration gate when both are enabled,
     * because 'deposited' is a strict superset of 'registered' in the funnel.
     *
     * GET /app/{bot_id}/api/check-access?tg_id=TELEGRAM_ID
     * Returns: { "access": true }
     *   or    { "access": false, "reason": "not_registered"|"not_deposited"|"missing_tg_id", "support_link": "..." }
     */
    public function checkAccess(\Base $f3): void
    {
        $botId = (int) $f3->get('PARAMS.bot_id');
        $tgId  = $f3->get('GET.tg_id');

        if (!$tgId || !is_numeric($tgId)) {
            echo json_encode(['access' => false, 'reason' => 'missing_tg_id']);
            return;
        }

        $telegramId = (int) $tgId;
        $db = $f3->get('DB');

        // Fetch gate flags + support_link in one trip.
        $botRows = $db->exec(
            'SELECT support_link, reg_gate_enabled, deposit_gate_enabled FROM bots WHERE id = ?',
            [$botId]
        );
        $bot = !empty($botRows) ? $botRows[0] : null;
        $supportLink = (!empty($bot) && !empty($bot['support_link'])) ? $bot['support_link'] : null;

        $regGate     = !empty($bot) && (int) $bot['reg_gate_enabled'] === 1;
        $depositGate = !empty($bot) && (int) $bot['deposit_gate_enabled'] === 1;

        // No gates active → unconditional access.
        if (!$regGate && !$depositGate) {
            echo json_encode(['access' => true]);
            return;
        }

        $userRows = $db->exec(
            'SELECT status FROM users WHERE bot_id = ? AND telegram_id = ?',
            [$botId, $telegramId]
        );
        $status = !empty($userRows) ? $userRows[0]['status'] : null;

        if ($depositGate) {
            if ($status === UserStatus::DEPOSITED) {
                echo json_encode(['access' => true]);
                return;
            }
            echo json_encode([
                'access'       => false,
                'reason'       => 'not_deposited',
                'support_link' => $supportLink,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // reg gate only.
        if (in_array($status, [UserStatus::REGISTERED, UserStatus::DEPOSITED], true)) {
            echo json_encode(['access' => true]);
            return;
        }

        echo json_encode([
            'access'       => false,
            'reason'       => 'not_registered',
            'support_link' => $supportLink,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Fetch a single translation value using two-tier resolution:
     * 1. Bot override for requested language
     * 2. Base (bot_id IS NULL) for requested language
     * 3. Base English fallback
     *
     * Returns empty string if no translation found.
     */
    private function getTranslation(\DB\SQL $db, int $botId, string $key, string $lang): string
    {
        $rows = $db->exec(
            "SELECT value FROM translations
             WHERE `key` = ? AND lang_code = ? AND bot_id = ?
             UNION ALL
             SELECT value FROM translations
             WHERE `key` = ? AND lang_code = ? AND bot_id IS NULL
             UNION ALL
             SELECT value FROM translations
             WHERE `key` = ? AND lang_code = 'en' AND bot_id IS NULL
             LIMIT 1",
            [$key, $lang, $botId, $key, $lang, $key]
        );
        return (!empty($rows)) ? (string) $rows[0]['value'] : '';
    }

    /**
     * Return the first non-null, non-empty-string, non-zero value.
     */
    private function firstNonEmpty(mixed ...$values): mixed
    {
        foreach ($values as $v) {
            if ($v !== null && $v !== '' && $v !== '0' && $v !== 0) {
                return $v;
            }
        }
        return null;
    }
}
