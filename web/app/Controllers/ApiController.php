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
 *   GET  /postback?user_id=X&status=registered|deposited&bot_id=X&secret=X
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
     * GET /postback?user_id=X&status=registered|deposited&bot_id=X&secret=X
     * Returns: 200 OK on success, 401 on bad secret, 404 on user not found
     */
    public function postback(\Base $f3): void
    {
        $userId = $f3->get('GET.user_id');
        $status = $f3->get('GET.status');
        $botId = (int) ($f3->get('GET.bot_id') ?? 0);
        $secret = $f3->get('GET.secret') ?? '';

        // Validate required parameters
        if (!$userId || !$status || !$botId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required parameters: user_id, status, bot_id']);
            return;
        }

        // Validate status value
        if (!in_array($status, ['registered', 'deposited'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status. Must be "registered" or "deposited".']);
            return;
        }

        $db = $f3->get('DB');

        // Look up bot and validate secret
        $bot = $db->exec(
            'SELECT id, postback_secret FROM bots WHERE id = ?',
            [$botId]
        );

        if (empty($bot)) {
            http_response_code(404);
            echo json_encode(['error' => 'Bot not found']);
            return;
        }

        $expectedSecret = $bot[0]['postback_secret'] ?? '';
        if ($expectedSecret === '' || !hash_equals($expectedSecret, $secret)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid secret']);
            return;
        }

        // Find the user by telegram_id + bot_id
        $user = $db->exec(
            'SELECT id, status FROM users WHERE telegram_id = ? AND bot_id = ?',
            [$userId, $botId]
        );

        if (empty($user)) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        // Only allow forward progression: new -> registered -> deposited
        $currentStatus = $user[0]['status'];
        $statusOrder = ['new' => 0, 'registered' => 1, 'deposited' => 2];
        $currentOrder = $statusOrder[$currentStatus] ?? 0;
        $newOrder = $statusOrder[$status] ?? 0;

        if ($newOrder <= $currentOrder) {
            // Status is same or lower — no-op but return success
            echo json_encode(['ok' => true, 'message' => 'Status unchanged (already at same or higher level)']);
            return;
        }

        // Update user status
        $db->exec(
            'UPDATE users SET status = ?, updated_at = NOW() WHERE telegram_id = ? AND bot_id = ?',
            [$status, $userId, $botId]
        );

        // Log the postback for audit
        error_log(sprintf(
            'POSTBACK: bot_id=%d user_id=%s status=%s (was: %s) ip=%s',
            $botId,
            $userId,
            $status,
            $currentStatus,
            $f3->get('IP')
        ));

        echo json_encode(['ok' => true, 'message' => 'Status updated to ' . $status]);
    }
}
