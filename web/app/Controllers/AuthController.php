<?php

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

/**
 * Handles admin panel authentication.
 * - Login form with CSRF protection
 * - Rate limiting: max 5 attempts per minute per IP
 * - Bcrypt password verification
 * - Session-based auth with 30min expiry
 */
class AuthController
{
    /**
     * Display login form.
     * GET /admin/login
     */
    public function loginForm(\Base $f3): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Already logged in? Redirect to bots
        if (!empty($_SESSION['admin_id'])) {
            $f3->reroute('/admin/bots');
            return;
        }

        // Generate CSRF token
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $f3->set('csrf_token', $_SESSION['csrf_token']);
        $f3->set('error', $f3->get('SESSION.login_error') ?? '');
        $f3->clear('SESSION.login_error');

        echo \Template::instance()->render('admin/login.html');
    }

    /**
     * Process login form submission.
     * POST /admin/login
     */
    public function login(\Base $f3): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // CSRF check
        $token = $f3->get('POST.csrf_token');
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $_SESSION['login_error'] = 'Invalid request. Please try again.';
            $f3->reroute('/admin/login');
            return;
        }

        // Rate limiting: max 5 attempts per minute per IP
        $ip = $f3->get('IP');
        $cacheKey = 'login_attempts_' . md5($ip);
        $attempts = $f3->get($cacheKey) ?: ['count' => 0, 'window_start' => time()];

        // Reset window if more than 60 seconds elapsed
        if ((time() - $attempts['window_start']) > 60) {
            $attempts = ['count' => 0, 'window_start' => time()];
        }

        if ($attempts['count'] >= 5) {
            $_SESSION['login_error'] = 'Too many login attempts. Please wait a minute.';
            $f3->reroute('/admin/login');
            return;
        }

        // Increment attempt counter (stored in DB-backed or file cache)
        $attempts['count']++;
        $f3->set($cacheKey, $attempts, 60);

        $username = trim($f3->get('POST.username') ?? '');
        $password = $f3->get('POST.password') ?? '';

        if ($username === '' || $password === '') {
            $_SESSION['login_error'] = 'Username and password are required.';
            $f3->reroute('/admin/login');
            return;
        }

        // Look up admin user
        $db = $f3->get('DB');
        $result = $db->exec(
            'SELECT id, username, password FROM admin_users WHERE username = ?',
            [$username]
        );

        if (empty($result) || !password_verify($password, $result[0]['password'])) {
            $_SESSION['login_error'] = 'Invalid username or password.';
            $f3->reroute('/admin/login');
            return;
        }

        // Success — create admin session
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $result[0]['id'];
        $_SESSION['admin_username'] = $result[0]['username'];
        $_SESSION['admin_last_activity'] = time();

        // Reset rate limiter on success
        $f3->clear($cacheKey);

        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $f3->reroute('/admin/bots');
    }

    /**
     * Logout and destroy session.
     * GET /admin/logout
     */
    public function logout(\Base $f3): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        AuthMiddleware::destroySession();
        $f3->reroute('/admin/login');
    }
}
