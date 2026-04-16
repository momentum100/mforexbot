<?php

namespace App\Controllers;

/**
 * Admin panel root controller.
 *
 * Holds only cross-cutting admin actions that don't belong to any single
 * resource: the /admin root redirect and the launcher-restart endpoint.
 *
 * Feature-specific admin pages (bots, users, settings, translations,
 * postbacks) now live in their own controllers — see BotsController,
 * UsersController, SettingsController, TranslationsController,
 * PostbacksController.
 *
 * All admin controllers share the beforeroute auth check via
 * AdminBaseController.
 */
class AdminController extends AdminBaseController
{
    /**
     * Redirect /admin to /admin/bots
     */
    public function redirectToBots(\Base $f3): void
    {
        $f3->reroute('/admin/bots');
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
}
