<?php

namespace App\Services;

/**
 * Lightweight static helper for sending Telegram Bot API messages.
 *
 * Uses stream_context_create + file_get_contents (no cURL dependency).
 */
class TelegramNotifier
{
    /**
     * POST a JSON payload to the Telegram Bot API sendMessage endpoint.
     *
     * @param string $token  Bot API token
     * @param array  $payload  Full sendMessage payload (chat_id, text, etc.)
     * @return bool  true on success, false on failure (logged)
     */
    public static function send(string $token, array $payload): bool
    {
        $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => 'Content-Type: application/json',
                'content'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
        ]);
        try {
            $result = @file_get_contents($url, false, $ctx);
            if ($result === false) {
                error_log('TelegramNotifier::send failed for chat_id='
                    . ($payload['chat_id'] ?? '?') . ': '
                    . (error_get_last()['message'] ?? 'unknown'));
                return false;
            }
            $decoded = json_decode($result, true);
            if (isset($decoded['ok']) && $decoded['ok'] === false) {
                error_log('TelegramNotifier::send API error for chat_id='
                    . ($payload['chat_id'] ?? '?') . ': '
                    . ($decoded['description'] ?? $result));
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            error_log('TelegramNotifier::send exception for chat_id='
                . ($payload['chat_id'] ?? '?') . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convenience wrapper: send a text message to a chat.
     *
     * @param string     $token   Bot API token
     * @param int|string $chatId  Telegram chat/user/group ID
     * @param string     $text    Message text
     * @param array      $extra   Optional keys (parse_mode, disable_web_page_preview, etc.)
     * @return bool
     */
    public static function sendMessage(string $token, int|string $chatId, string $text, array $extra = []): bool
    {
        return self::send($token, array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
        ], $extra));
    }
}
