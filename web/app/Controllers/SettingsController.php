<?php

namespace App\Controllers;

use App\Services\SettingsService;

/**
 * /admin/settings — edit the global (non-per-bot) settings table.
 *
 * Currently only exposes `postback_base_url`; more keys can be rendered into
 * settings.html without a schema change since the store is key/value.
 */
class SettingsController extends AdminBaseController
{
    /**
     * Global settings page.
     * GET /admin/settings
     */
    public function settings(\Base $f3): void
    {
        $db = $f3->get('DB');

        $f3->set('postback_base_url', SettingsService::get($db, 'postback_base_url') ?? '');
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
            SettingsService::set($db, 'postback_base_url', null);
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

        SettingsService::set($db, 'postback_base_url', $raw);
        $_SESSION['flash'] = 'Настройки сохранены.';
        $f3->reroute('/admin/settings');
    }
}
