<?php

namespace App\Controllers;

/**
 * /admin/postbacks — read-only browser for the append-only postback_events
 * log.
 *
 * Writes come from ApiController::postback. This controller never mutates
 * the table; it just filters + paginates for the operator.
 */
class PostbacksController extends AdminBaseController
{
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
}
