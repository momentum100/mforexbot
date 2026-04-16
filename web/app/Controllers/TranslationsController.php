<?php

namespace App\Controllers;

/**
 * /admin/translations — manage the two-tier translation store.
 *
 * Tier 1: base (translations.bot_id IS NULL) — shared across all bots.
 * Tier 2: bot override (translations.bot_id = X) — wins over base at
 *         resolution time.
 *
 * The index page merges both tiers for the currently selected bot + lang so
 * the operator can see where each key resolves from. saveTranslation /
 * deleteTranslation respond JSON (they're called from inline editors on
 * translations.html).
 */
class TranslationsController extends AdminBaseController
{
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
