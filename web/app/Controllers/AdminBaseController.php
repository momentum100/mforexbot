<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

/**
 * Common base for all /admin/* controllers.
 *
 * Centralises the beforeroute hook so every admin controller enforces the
 * same auth check + surfaces admin_username to all views. Each dedicated
 * admin controller (BotsController, UsersController, etc.) extends this and
 * only implements its own actions.
 */
abstract class AdminBaseController
{
    /**
     * F3 hook — runs before every route in the extending controller.
     * Enforces admin authentication.
     */
    public function beforeroute(\Base $f3): void
    {
        AuthMiddleware::check($f3);

        // Make admin info available to all views
        $f3->set('admin_username', $_SESSION['admin_username'] ?? '');
    }
}
