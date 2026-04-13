<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

/**
 * Admin panel controller.
 * - Bots list with user counts
 * - Translations CRUD with two-tier resolution (base + bot override)
 *
 * All routes require admin session (enforced via beforeroute).
 */
class AdminController
{
    /**
     * F3 hook — runs before every route in this controller.
     * Enforces admin authentication.
     */
    public function beforeroute(\Base $f3): void
    {
        AuthMiddleware::check($f3);

        // Make admin info available to all views
        $f3->set('admin_username', $_SESSION['admin_username'] ?? '');
    }

    /**
     * Redirect /admin to /admin/bots
     */
    public function redirectToBots(\Base $f3): void
    {
        $f3->reroute('/admin/bots');
    }

    /**
     * Bots list page.
     * GET /admin/bots
     */
    public function bots(\Base $f3): void
    {
        $db = $f3->get('DB');

        $bots = $db->exec(
            'SELECT b.*, (SELECT COUNT(*) FROM users u WHERE u.bot_id = b.id) AS user_count
             FROM bots b
             ORDER BY b.id ASC'
        );

        $f3->set('bots', $bots);
        $f3->set('bots_json', json_encode($bots, JSON_UNESCAPED_UNICODE));
        $f3->set('csrf_token', $_SESSION['csrf_token'] ?? '');
        $f3->set('page_title', 'Bots');

        echo \Template::instance()->render('admin/bots.html');
    }

    /**
     * Save bot (create or update).
     * POST /admin/bots
     */
    public function saveBots(\Base $f3): void
    {
        // CSRF check
        $token = $f3->get('POST.csrf_token');
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $f3->error(403, 'Invalid CSRF token');
            return;
        }

        $db = $f3->get('DB');
        $id = (int) $f3->get('POST.id');
        $name = trim($f3->get('POST.name') ?? '');
        $botToken = trim($f3->get('POST.token') ?? '');
        $webappUrl = trim($f3->get('POST.webapp_url') ?? '') ?: null;
        $linkedChannel = trim($f3->get('POST.linked_channel') ?? '') ?: null;
        $supportLink = trim($f3->get('POST.support_link') ?? '') ?: null;
        $referralUrlTemplate = trim($f3->get('POST.referral_url_template') ?? '') ?: null;
        $postbackSecret = trim($f3->get('POST.postback_secret') ?? '') ?: null;

        if ($name === '' || $botToken === '') {
            $f3->reroute('/admin/bots');
            return;
        }

        if ($id > 0) {
            // Update existing bot
            $db->exec(
                'UPDATE bots SET name = ?, token = ?, webapp_url = ?, linked_channel = ?,
                 support_link = ?, referral_url_template = ?, postback_secret = ?,
                 updated_at = NOW()
                 WHERE id = ?',
                [$name, $botToken, $webappUrl, $linkedChannel, $supportLink, $referralUrlTemplate, $postbackSecret, $id]
            );
        } else {
            // Create new bot
            $db->exec(
                'INSERT INTO bots (name, token, webapp_url, linked_channel, support_link, referral_url_template, postback_secret)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$name, $botToken, $webappUrl, $linkedChannel, $supportLink, $referralUrlTemplate, $postbackSecret]
            );
        }

        $f3->reroute('/admin/bots');
    }

    /**
     * Translations manager page.
     * GET /admin/translations?bot_id=X&lang=XX
     */
    public function translations(\Base $f3): void
    {
        $db = $f3->get('DB');

        // Load all bots for the selector dropdown
        $bots = $db->exec('SELECT id, name FROM bots ORDER BY id ASC');
        $f3->set('bots', $bots);

        // Load all supported languages
        $languages = $db->exec('SELECT code, name, native_name, flag FROM languages ORDER BY sort_order ASC');
        $f3->set('languages', $languages);

        // Current selections
        $botId = $f3->get('GET.bot_id');
        $lang = $f3->get('GET.lang') ?: 'en';
        $search = trim($f3->get('GET.search') ?? '');

        $f3->set('current_bot_id', $botId);
        $f3->set('current_lang', $lang);
        $f3->set('search', $search);

        // Build translations table
        // Get all unique keys (from base + bot override)
        $searchCondition = '';
        $params = [$lang];

        if ($search !== '') {
            $searchCondition = ' AND t.`key` LIKE ?';
            $params[] = '%' . $search . '%';
        }

        // Get base translations for this language
        $baseQuery = 'SELECT t.`key`, t.value
                      FROM translations t
                      WHERE t.bot_id IS NULL AND t.lang_code = ?' . $searchCondition . '
                      ORDER BY t.`key` ASC';
        $baseTranslations = $db->exec($baseQuery, $params);
        $baseMap = [];
        foreach ($baseTranslations as $row) {
            $baseMap[$row['key']] = $row['value'];
        }

        // Get bot-specific overrides if a bot is selected
        $overrideMap = [];
        if ($botId !== null && $botId !== '' && $botId !== 'base') {
            $overrideParams = [(int) $botId, $lang];
            $overrideSearchCondition = '';
            if ($search !== '') {
                $overrideSearchCondition = ' AND t.`key` LIKE ?';
                $overrideParams[] = '%' . $search . '%';
            }

            $overrideQuery = 'SELECT t.`key`, t.value
                              FROM translations t
                              WHERE t.bot_id = ? AND t.lang_code = ?' . $overrideSearchCondition . '
                              ORDER BY t.`key` ASC';
            $overrideTranslations = $db->exec($overrideQuery, $overrideParams);
            foreach ($overrideTranslations as $row) {
                $overrideMap[$row['key']] = $row['value'];
            }
        }

        // Merge keys from both sources
        $allKeys = array_unique(array_merge(array_keys($baseMap), array_keys($overrideMap)));
        sort($allKeys);

        $translations = [];
        foreach ($allKeys as $key) {
            $translations[] = [
                'key' => $key,
                'base_value' => $baseMap[$key] ?? '',
                'override_value' => $overrideMap[$key] ?? '',
                'has_override' => isset($overrideMap[$key]),
                'is_missing_base' => !isset($baseMap[$key]),
            ];
        }

        $f3->set('translations', $translations);
        $f3->set('csrf_token', $_SESSION['csrf_token'] ?? '');
        $f3->set('page_title', 'Translations');

        echo \Template::instance()->render('admin/translations.html');
    }

    /**
     * Save a translation (base or bot override).
     * POST /admin/translations/save
     * Body: bot_id, key, lang_code, value, csrf_token
     */
    public function saveTranslation(\Base $f3): void
    {
        header('Content-Type: application/json');

        // CSRF check
        $token = $f3->get('POST.csrf_token');
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $f3->error(403, 'Invalid CSRF token');
            return;
        }

        $db = $f3->get('DB');
        $botId = $f3->get('POST.bot_id');
        $key = trim($f3->get('POST.key') ?? '');
        $langCode = trim($f3->get('POST.lang_code') ?? '');
        $value = $f3->get('POST.value') ?? '';

        if ($key === '' || $langCode === '') {
            echo json_encode(['success' => false, 'error' => 'Key and language are required']);
            return;
        }

        // Determine if this is a base translation or bot override
        if ($botId === null || $botId === '' || $botId === 'base') {
            // Base translation (bot_id IS NULL)
            $existing = $db->exec(
                'SELECT id FROM translations WHERE bot_id IS NULL AND `key` = ? AND lang_code = ?',
                [$key, $langCode]
            );

            if (!empty($existing)) {
                $db->exec(
                    'UPDATE translations SET value = ?, updated_at = NOW() WHERE bot_id IS NULL AND `key` = ? AND lang_code = ?',
                    [$value, $key, $langCode]
                );
            } else {
                $db->exec(
                    'INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES (NULL, ?, ?, ?)',
                    [$key, $langCode, $value]
                );
            }
        } else {
            // Bot-specific override
            $bid = (int) $botId;
            $existing = $db->exec(
                'SELECT id FROM translations WHERE bot_id = ? AND `key` = ? AND lang_code = ?',
                [$bid, $key, $langCode]
            );

            if (!empty($existing)) {
                $db->exec(
                    'UPDATE translations SET value = ?, updated_at = NOW() WHERE bot_id = ? AND `key` = ? AND lang_code = ?',
                    [$value, $bid, $key, $langCode]
                );
            } else {
                $db->exec(
                    'INSERT INTO translations (bot_id, `key`, lang_code, value) VALUES (?, ?, ?, ?)',
                    [$bid, $key, $langCode, $value]
                );
            }
        }

        echo json_encode(['success' => true]);
    }

    /**
     * Delete a bot-specific translation override (reset to base).
     * POST /admin/translations/delete
     * Body: bot_id, key, lang_code, csrf_token
     */
    public function deleteTranslation(\Base $f3): void
    {
        header('Content-Type: application/json');

        // CSRF check
        $token = $f3->get('POST.csrf_token');
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $f3->error(403, 'Invalid CSRF token');
            return;
        }

        $db = $f3->get('DB');
        $botId = $f3->get('POST.bot_id');
        $key = trim($f3->get('POST.key') ?? '');
        $langCode = trim($f3->get('POST.lang_code') ?? '');

        if ($key === '' || $langCode === '') {
            echo json_encode(['success' => false, 'error' => 'Key and language are required']);
            return;
        }

        if ($botId !== null && $botId !== '' && $botId !== 'base') {
            // Delete bot override only (not base)
            $db->exec(
                'DELETE FROM translations WHERE bot_id = ? AND `key` = ? AND lang_code = ?',
                [(int) $botId, $key, $langCode]
            );
        }

        echo json_encode(['success' => true]);
    }
}
