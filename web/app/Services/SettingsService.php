<?php

namespace App\Services;

/**
 * Tiny helper around the global `settings` table (migration 017).
 *
 * The `settings` table is the one place in the schema that is NOT scoped by
 * bot_id — it holds truly global platform config (e.g. postback_base_url).
 * This service centralises the get/set pair so controllers don't repeat the
 * same SELECT / INSERT ... ON DUPLICATE KEY UPDATE snippets.
 */
class SettingsService
{
    /**
     * Return the string value for a settings key, or null when the row is
     * missing / stored as NULL / stored as empty string.
     */
    public static function get(\DB\SQL $db, string $key): ?string
    {
        $rows = $db->exec('SELECT `value` FROM settings WHERE `key` = ?', [$key]);
        if (empty($rows)) {
            return null;
        }
        $v = $rows[0]['value'] ?? null;
        return ($v === null || $v === '') ? null : (string) $v;
    }

    /**
     * Upsert a value into the global `settings` table.
     * Null clears the value (we keep the row for stability).
     */
    public static function set(\DB\SQL $db, string $key, ?string $value): void
    {
        $db->exec(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()',
            [$key, $value]
        );
    }
}
