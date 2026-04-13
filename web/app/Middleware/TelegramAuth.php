<?php

namespace App\Middleware;

/**
 * Validates Telegram initData HMAC-SHA256 hash on all /app/ API routes.
 *
 * Telegram Mini Apps send initData as a query string containing user info
 * and a hash. We verify the hash using the bot token to ensure authenticity.
 *
 * See: https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
class TelegramAuth
{
    /** Maximum age of initData before it's considered expired (5 minutes) */
    private const MAX_AGE_SECONDS = 300;

    /**
     * Validate Telegram initData from the request.
     * Returns the parsed user data on success, or null on failure.
     *
     * @param string $initData Raw initData string from Telegram
     * @param string $botToken Bot API token used as HMAC secret
     * @return array|null Parsed data on success, null on invalid/expired
     */
    public static function validate(string $initData, string $botToken): ?array
    {
        if (empty($initData) || empty($botToken)) {
            return null;
        }

        // Parse the initData query string
        parse_str($initData, $parsed);

        if (empty($parsed['hash'])) {
            return null;
        }

        $hash = $parsed['hash'];
        unset($parsed['hash']);

        // Sort parameters alphabetically by key
        ksort($parsed);

        // Build the data-check-string: "key=value\nkey=value\n..."
        $dataCheckParts = [];
        foreach ($parsed as $key => $value) {
            $dataCheckParts[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckParts);

        // Generate secret key: HMAC-SHA256 of bot token with "WebAppData" as key
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);

        // Calculate expected hash
        $expectedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

        // Constant-time comparison
        if (!hash_equals($expectedHash, $hash)) {
            return null;
        }

        // Check expiry (auth_date must be within MAX_AGE_SECONDS)
        if (!empty($parsed['auth_date'])) {
            $authDate = (int) $parsed['auth_date'];
            if ((time() - $authDate) > self::MAX_AGE_SECONDS) {
                return null;
            }
        }

        return $parsed;
    }

    /**
     * Middleware check for F3 routes.
     * Reads initData from X-Telegram-Init-Data header or Authorization header.
     * Sets TELEGRAM_USER in hive on success, aborts 401 on failure.
     */
    public static function check(\Base $f3): void
    {
        $botId = (int) $f3->get('PARAMS.bot_id');

        // Look up bot token from DB
        $db = $f3->get('DB');
        $result = $db->exec(
            'SELECT token FROM bots WHERE id = ?',
            [$botId]
        );

        if (empty($result)) {
            $f3->error(404, 'Bot not found');
            return;
        }

        $botToken = $result[0]['token'];

        // Get initData from headers
        $initData = $f3->get('HEADERS.X-Telegram-Init-Data')
            ?: $f3->get('HEADERS.Authorization');

        // Strip "tma " prefix if present (Telegram convention)
        if ($initData && str_starts_with(strtolower($initData), 'tma ')) {
            $initData = substr($initData, 4);
        }

        $userData = self::validate($initData ?? '', $botToken);

        if ($userData === null) {
            header('Content-Type: application/json');
            $f3->error(401, 'Invalid or expired Telegram authentication');
            return;
        }

        // Store validated user data in F3 hive for controllers
        $f3->set('TELEGRAM_USER', $userData);
    }
}
