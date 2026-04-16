<?php

namespace App\Controllers;

/**
 * /admin/users — browse, delete, and toggle is_admin on rows from the global
 * users table.
 *
 * Deletion is straightforward: postback_events.telegram_id is not a FK to
 * users.id, so DELETE FROM users doesn't cascade against anything expected.
 * We still DELETE matching postback_events rows by (bot_id, telegram_id) so
 * the operator gets a clean slate when they remove a user.
 */
class UsersController extends AdminBaseController
{
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
}
