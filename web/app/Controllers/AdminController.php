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

        // Global postback base URL (from settings table, migration 017).
        // Empty/NULL until admin fills it in via /admin/settings.
        $postbackBaseUrl = $this->getSetting($db, 'postback_base_url') ?? '';

        $f3->set('bots', $bots);
        $f3->set('bots_json', json_encode($bots, JSON_UNESCAPED_UNICODE));
        $f3->set('postback_base_url', $postbackBaseUrl);
        $f3->set('postback_base_url_json', json_encode($postbackBaseUrl, JSON_UNESCAPED_UNICODE));
        $f3->set('csrf_token', $_SESSION['csrf_token'] ?? '');
        $f3->set('page_title', 'Bots');

        // One-shot flash message (set by restartBots, consumed here).
        $flash = $_SESSION['flash'] ?? '';
        unset($_SESSION['flash']);
        $f3->set('flash', $flash);

        echo \Template::instance()->render('admin/bots.html');
    }

    /**
     * Tiny helper around the global `settings` table (migration 017).
     * Returns string|null — null when the row exists with value=NULL or is missing.
     */
    private function getSetting(\DB\SQL $db, string $key): ?string
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
    private function setSetting(\DB\SQL $db, string $key, ?string $value): void
    {
        $db->exec(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()',
            [$key, $value]
        );
    }

    /**
     * Global settings page.
     * GET /admin/settings
     */
    public function settings(\Base $f3): void
    {
        $db = $f3->get('DB');

        $f3->set('postback_base_url', $this->getSetting($db, 'postback_base_url') ?? '');
        $f3->set('csrf_token', $_SESSION['csrf_token'] ?? '');
        $f3->set('page_title', 'Settings');

        $flash = $_SESSION['flash'] ?? '';
        unset($_SESSION['flash']);
        $f3->set('flash', $flash);

        echo \Template::instance()->render('admin/settings.html');
    }

    /**
     * Save global settings.
     * POST /admin/settings
     *
     * Currently only `postback_base_url` is edited from the UI. Trimmed,
     * stripped trailing slash, stored as NULL when empty. Non-http(s) values
     * are rejected with a flash error.
     */
    public function saveSettings(\Base $f3): void
    {
        $token = $f3->get('POST.csrf_token');
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $f3->error(403, 'Invalid CSRF token');
            return;
        }

        $db = $f3->get('DB');

        $raw = trim((string) $f3->get('POST.postback_base_url'));
        if ($raw === '') {
            $this->setSetting($db, 'postback_base_url', null);
            $_SESSION['flash'] = 'Postback Base URL очищен.';
            $f3->reroute('/admin/settings');
            return;
        }

        // Strip a trailing slash so we can unconditionally append `/postback?...`.
        $raw = rtrim($raw, '/');

        // Defensive: if admin pasted the full postback endpoint (e.g.
        // `https://mbot.example.com/postback`), strip the `/postback` suffix
        // so the stored value is a true base URL. Only strips an exact
        // trailing `/postback` path component, not `/postback-something`.
        $raw = preg_replace('#/postback$#', '', $raw);

        if (!preg_match('#^https?://#i', $raw)) {
            $_SESSION['flash'] = 'Ошибка: URL должен начинаться с http:// или https://';
            $f3->reroute('/admin/settings');
            return;
        }

        $this->setSetting($db, 'postback_base_url', $raw);
        $_SESSION['flash'] = 'Настройки сохранены.';
        $f3->reroute('/admin/settings');
    }

    /**
     * Request a launcher restart.
     * POST /admin/bots/restart
     *
     * Writes NOW() into the singleton `system` row. The Python launcher
     * polls that row every ~5s; a new timestamp causes it to sys.exit(0),
     * Docker respawns it, and the fresh process loads the current
     * bots.is_active set.
     */
    public function restartBots(\Base $f3): void
    {
        // CSRF check (same pattern as saveBots).
        $token = $f3->get('POST.csrf_token');
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $f3->error(403, 'Invalid CSRF token');
            return;
        }

        $db = $f3->get('DB');
        $db->exec('UPDATE system SET restart_requested_at = NOW() WHERE id = 1');

        $_SESSION['flash'] = 'Перезапуск запрошен — боты перезагрузятся в течение 10 секунд.';
        $f3->reroute('/admin/bots');
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
        $linkedChannelId = trim($f3->get('POST.linked_channel_id') ?? '') ?: null;
        $adminGroupId = trim($f3->get('POST.admin_group_id') ?? '') ?: null;
        $supportLink = trim($f3->get('POST.support_link') ?? '') ?: null;
        $referralUrlTemplate = trim($f3->get('POST.referral_url_template') ?? '') ?: null;
        $postbackSecret = trim($f3->get('POST.postback_secret') ?? '') ?: null;
        // Plaintext bot-wide access password (migration 015). Empty → NULL (gate disabled).
        // Per product decision: no hashing, admin reads/edits as-is.
        $accessPassword = trim($f3->get('POST.access_password') ?? '') ?: null;
        // Checkbox: present in POST when checked, absent when not.
        $isActive = $f3->get('POST.is_active') ? 1 : 0;

        if ($name === '' || $botToken === '') {
            $f3->reroute('/admin/bots');
            return;
        }

        if ($id > 0) {
            // Update existing bot
            $db->exec(
                'UPDATE bots SET name = ?, token = ?, webapp_url = ?, linked_channel = ?, linked_channel_id = ?,
                 admin_group_id = ?, support_link = ?, referral_url_template = ?, postback_secret = ?, access_password = ?, is_active = ?,
                 updated_at = NOW()
                 WHERE id = ?',
                [$name, $botToken, $webappUrl, $linkedChannel, $linkedChannelId, $adminGroupId, $supportLink, $referralUrlTemplate, $postbackSecret, $accessPassword, $isActive, $id]
            );
        } else {
            // Create new bot
            $db->exec(
                'INSERT INTO bots (name, token, webapp_url, linked_channel, linked_channel_id, admin_group_id, support_link, referral_url_template, postback_secret, access_password, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$name, $botToken, $webappUrl, $linkedChannel, $linkedChannelId, $adminGroupId, $supportLink, $referralUrlTemplate, $postbackSecret, $accessPassword, $isActive]
            );
        }

        $f3->reroute('/admin/bots');
    }

    /**
     * Users list page.
     * GET /admin/users?bot_id=&q=&page=
     *
     * Lists rows from the global `users` table across all bots. Filters:
     *   - bot_id — exact match on users.bot_id (empty = all bots)
     *   - q      — case-insensitive LIKE on telegram_id, username, first_name
     *   - page   — 1-based pagination, 50 per page
     *
     * Note: deletion is simple — `postback_events.telegram_id` is not a FK,
     * so user rows can be deleted without cascading postback history.
     */
    public function users(\Base $f3): void
    {
        $db = $f3->get('DB');

        // Bot filter dropdown.
        $bots = $db->exec('SELECT id, name FROM bots ORDER BY id ASC');

        // Filter inputs.
        $botIdRaw = trim((string) $f3->get('GET.bot_id'));
        $q = trim((string) $f3->get('GET.q'));
        $page = max(1, (int) $f3->get('GET.page'));
        $perPage = 50;

        $where = [];
        $params = [];

        if ($botIdRaw !== '' && ctype_digit($botIdRaw)) {
            $where[] = 'u.bot_id = ?';
            $params[] = (int) $botIdRaw;
        }

        if ($q !== '') {
            // Case-insensitive match on telegram_id (cast to CHAR), username, first_name.
            $where[] = '(CAST(u.telegram_id AS CHAR) LIKE ? OR LOWER(COALESCE(u.username,\'\')) LIKE LOWER(?) OR LOWER(COALESCE(u.first_name,\'\')) LIKE LOWER(?))';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

        // Total count for pagination.
        $countRow = $db->exec('SELECT COUNT(*) AS c FROM users u' . $whereSql, $params);
        $total = (int) ($countRow[0]['c'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        // Page of users + bot name join.
        $listParams = array_merge($params, [$perPage, $offset]);
        $users = $db->exec(
            'SELECT u.id, u.bot_id, b.name AS bot_name, u.telegram_id, u.username, u.first_name,
                    u.lang_code, u.status, u.is_admin, u.password_passed, u.created_at
             FROM users u
             LEFT JOIN bots b ON b.id = u.bot_id'
            . $whereSql .
            ' ORDER BY u.id DESC
             LIMIT ? OFFSET ?',
            $listParams
        );

        $f3->set('bots', $bots);
        $f3->set('users', $users);
        $f3->set('current_bot_id', $botIdRaw);
        $f3->set('q', $q);
        $f3->set('page', $page);
        $f3->set('total_pages', $totalPages);
        $f3->set('total', $total);
        $f3->set('per_page', $perPage);
        $f3->set('csrf_token', $_SESSION['csrf_token'] ?? '');
        $f3->set('page_title', 'Users');

        $flash = $_SESSION['flash'] ?? '';
        $flashError = $_SESSION['flash_error'] ?? '';
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        $f3->set('flash', $flash);
        $f3->set('flash_error', $flashError);

        echo \Template::instance()->render('admin/users.html');
    }

    /**
     * Delete a user row.
     * POST /admin/users/@id/delete
     *
     * No cascading: postback_events uses telegram_id (not FK), so we simply
     * DELETE the user row. Any unexpected FK pointing at users.id is caught
     * and surfaced via a flash_error rather than silently swallowed.
     */
    public function deleteUser(\Base $f3): void
    {
        $token = $f3->get('POST.csrf_token');
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $f3->error(403, 'Invalid CSRF token');
            return;
        }

        $id = (int) $f3->get('PARAMS.id');
        $redirect = $this->buildUsersRedirect($f3);

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Некорректный ID пользователя.';
            $f3->reroute($redirect);
            return;
        }

        try {
            $db = $f3->get('DB');
            // Look up bot_id + telegram_id before deleting, to also purge postback_events.
            $user = $db->exec('SELECT bot_id, telegram_id FROM users WHERE id = ?', [$id]);
            $db->exec('DELETE FROM users WHERE id = ?', [$id]);
            if (!empty($user)) {
                $db->exec(
                    'DELETE FROM postback_events WHERE bot_id = ? AND telegram_id = ?',
                    [$user[0]['bot_id'], $user[0]['telegram_id']]
                );
            }
            $_SESSION['flash'] = 'Пользователь #' . $id . ' и его постбеки удалены.';
        } catch (\Throwable $e) {
            // Most likely cause: a FK from some other table points at users.id.
            $_SESSION['flash_error'] = 'Не удалось удалить пользователя: ' . $e->getMessage();
        }

        $f3->reroute($redirect);
    }

    /**
     * Toggle the is_admin flag on a user.
     * POST /admin/users/@id/toggle-admin
     */
    public function toggleUserAdmin(\Base $f3): void
    {
        $token = $f3->get('POST.csrf_token');
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $f3->error(403, 'Invalid CSRF token');
            return;
        }

        $id = (int) $f3->get('PARAMS.id');
        $redirect = $this->buildUsersRedirect($f3);

        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Некорректный ID пользователя.';
            $f3->reroute($redirect);
            return;
        }

        $db = $f3->get('DB');
        $db->exec('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?', [$id]);

        // Read the post-update value so the flash is accurate.
        $rows = $db->exec('SELECT is_admin FROM users WHERE id = ?', [$id]);
        $isAdmin = (int) ($rows[0]['is_admin'] ?? 0);
        $_SESSION['flash'] = $isAdmin ? 'Права администратора выданы.' : 'Права администратора отозваны.';

        $f3->reroute($redirect);
    }

    /**
     * Preserve filter/pagination state across POST → redirect → GET.
     * Reads hidden inputs posted by the action forms on users.html.
     */
    private function buildUsersRedirect(\Base $f3): string
    {
        $parts = [];
        $botId = trim((string) $f3->get('POST.return_bot_id'));
        $q = trim((string) $f3->get('POST.return_q'));
        $page = (int) $f3->get('POST.return_page');

        if ($botId !== '' && ctype_digit($botId)) {
            $parts['bot_id'] = $botId;
        }
        if ($q !== '') {
            $parts['q'] = $q;
        }
        if ($page > 1) {
            $parts['page'] = $page;
        }

        return '/admin/users' . (empty($parts) ? '' : ('?' . http_build_query($parts)));
    }

    /**
     * Postback events log page.
     * GET /admin/postbacks?bot_id=&event_type=&auth_status=&q=&page=
     *
     * Read-only view of the append-only postback_events table. Filters:
     *   - bot_id      — exact match (empty = all bots)
     *   - event_type  — exact match (empty = all)
     *   - auth_status — exact match (empty = all)
     *   - q           — LIKE on telegram_id
     *   - page        — 1-based pagination, 50 per page
     */
    public function postbacks(\Base $f3): void
    {
        $db = $f3->get('DB');

        // Bot filter dropdown.
        $bots = $db->exec('SELECT id, name FROM bots ORDER BY id ASC');

        // Filter inputs.
        $botIdRaw = trim((string) $f3->get('GET.bot_id'));
        $eventType = trim((string) $f3->get('GET.event_type'));
        $authStatus = trim((string) $f3->get('GET.auth_status'));
        $q = trim((string) $f3->get('GET.q'));
        $page = max(1, (int) $f3->get('GET.page'));
        $perPage = 50;

        $where = [];
        $params = [];

        if ($botIdRaw !== '' && ctype_digit($botIdRaw)) {
            $where[] = 'pe.bot_id = ?';
            $params[] = (int) $botIdRaw;
        }

        if ($eventType !== '') {
            $where[] = 'pe.event_type = ?';
            $params[] = $eventType;
        }

        if ($authStatus !== '') {
            $where[] = 'pe.auth_status = ?';
            $params[] = $authStatus;
        }

        if ($q !== '') {
            $where[] = 'CAST(pe.telegram_id AS CHAR) LIKE ?';
            $params[] = '%' . $q . '%';
        }

        $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

        // Total count for pagination.
        $countRow = $db->exec('SELECT COUNT(*) AS c FROM postback_events pe' . $whereSql, $params);
        $total = (int) ($countRow[0]['c'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        // Page of events + bot name join.
        $listParams = array_merge($params, [$perPage, $offset]);
        $events = $db->exec(
            'SELECT pe.id, pe.bot_id, b.name AS bot_name, pe.telegram_id, pe.event_type,
                    pe.auth_status, pe.params_json, pe.raw_query, pe.ip, pe.received_at
             FROM postback_events pe
             LEFT JOIN bots b ON b.id = pe.bot_id'
            . $whereSql .
            ' ORDER BY pe.received_at DESC
             LIMIT ? OFFSET ?',
            $listParams
        );

        $f3->set('bots', $bots);
        $f3->set('events', $events);
        $f3->set('current_bot_id', $botIdRaw);
        $f3->set('current_event_type', $eventType);
        $f3->set('current_auth_status', $authStatus);
        $f3->set('q', $q);
        $f3->set('page', $page);
        $f3->set('total_pages', $totalPages);
        $f3->set('total', $total);
        $f3->set('per_page', $perPage);
        $f3->set('page_title', 'Postbacks');

        echo \Template::instance()->render('admin/postbacks.html');
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
