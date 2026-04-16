<?php

namespace App\Controllers;

use App\Middleware\TelegramAuth;

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
     */
    public function postback(\Base $f3): void
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

        $event = trim((string) ($this->firstNonEmpty(
            $f3->get('GET.event'),
            $f3->get('GET.status')
        ) ?? ''));
        $secret   = (string) ($f3->get('GET.secret') ?? '');
        $telegramId = (is_numeric($clickId) && (int) $clickId > 0) ? (int) $clickId : null;

        $rawQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $rawQuery = preg_replace('/(^|&)secret=[^&]*/', '$1secret=REDACTED', $rawQuery);

        // Classify request so we can log it and still return an accurate status.
        $authStatus = 'ok';
        $httpCode   = 200;
        $response   = ['ok' => true];

        $db = $f3->get('DB');

        if ($botId === null || $event === '') {
            $authStatus = 'missing_params';
            $httpCode   = 400;
            $response   = ['error' => 'Missing required parameters: bot_id (sub_id1/bot_id/site_id), event'];
        } else {
            $bot = $db->exec('SELECT id, postback_secret FROM bots WHERE id = ?', [$botId]);
            if (empty($bot)) {
                $authStatus = 'unknown_bot';
                $httpCode   = 404;
                $response   = ['error' => 'Bot not found'];
                // Keep bot_id for the log row even though FK will reject — store NULL to allow insert.
                $botIdForLog = null;
            } else {
                $expectedSecret = $bot[0]['postback_secret'] ?? '';
                if ($expectedSecret === '' || !hash_equals($expectedSecret, $secret)) {
                    $authStatus = 'bad_secret';
                    $httpCode   = 401;
                    $response   = ['error' => 'Invalid secret'];
                }
                $botIdForLog = $botId;
            }
        }

        // Log EVERY request (accepted or not) for debugging.
        $db->exec(
            'INSERT INTO postback_events (bot_id, telegram_id, event_type, auth_status, params_json, raw_query, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $botIdForLog ?? $botId,
                $telegramId,
                $event !== '' ? $event : null,
                $authStatus,
                json_encode($params, JSON_UNESCAPED_UNICODE),
                $rawQuery,
                $f3->get('IP'),
            ]
        );

        http_response_code($httpCode);
        echo json_encode($response);
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
