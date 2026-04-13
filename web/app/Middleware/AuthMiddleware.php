<?php

namespace App\Middleware;

/**
 * Authentication middleware for admin panel routes.
 * Checks for valid admin session with 30-minute inactivity expiry.
 *
 * Usage: Call AuthMiddleware::check($f3) in controller's beforeroute().
 */
class AuthMiddleware
{
    /** Session inactivity timeout in seconds (30 minutes) */
    private const SESSION_TIMEOUT = 1800;

    /**
     * Verify admin session is valid and not expired.
     * Redirects to login page if session is invalid.
     */
    public static function check(\Base $f3): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $isValid = false;

        if (!empty($_SESSION['admin_id']) && !empty($_SESSION['admin_last_activity'])) {
            $elapsed = time() - $_SESSION['admin_last_activity'];
            if ($elapsed < self::SESSION_TIMEOUT) {
                // Session is valid — refresh activity timestamp
                $_SESSION['admin_last_activity'] = time();
                $isValid = true;
            } else {
                // Session expired — destroy it
                self::destroySession();
            }
        }

        if (!$isValid) {
            $f3->reroute('/admin/login');
        }
    }

    /**
     * Destroy the current admin session completely.
     */
    public static function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }
        session_destroy();
    }
}
