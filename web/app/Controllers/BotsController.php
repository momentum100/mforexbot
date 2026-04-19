<?php

namespace App\Controllers;

use App\Services\SettingsService;

/**
 * /admin/bots — list and create/update bot rows.
 *
 * The list page also shows the global postback_base_url (read from
 * SettingsService) so admins can preview the rendered postback URL next to
 * each bot's secret. Saving a bot auto-fills webapp_url from
 * postback_base_url when the admin left the field blank.
 */
class BotsController extends AdminBaseController
{
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
        $postbackBaseUrl = SettingsService::get($db, 'postback_base_url') ?? '';

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
        // Access-gate toggles (migration 022). Each flag only takes effect when
        // the paired field is filled in (channel id / referral url / password);
        // enabling a flag without the paired field is a silent no-op at runtime.
        $channelGateEnabled  = $f3->get('POST.channel_gate_enabled')  ? 1 : 0;
        $regGateEnabled      = $f3->get('POST.reg_gate_enabled')      ? 1 : 0;
        $passwordGateEnabled = $f3->get('POST.password_gate_enabled') ? 1 : 0;
        $depositGateEnabled  = $f3->get('POST.deposit_gate_enabled')  ? 1 : 0;
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
                 admin_group_id = ?, support_link = ?, referral_url_template = ?, postback_secret = ?, access_password = ?,
                 channel_gate_enabled = ?, reg_gate_enabled = ?, password_gate_enabled = ?, deposit_gate_enabled = ?,
                 is_active = ?,
                 updated_at = NOW()
                 WHERE id = ?',
                [$name, $botToken, $webappUrl, $linkedChannel, $linkedChannelId, $adminGroupId, $supportLink, $referralUrlTemplate, $postbackSecret, $accessPassword,
                 $channelGateEnabled, $regGateEnabled, $passwordGateEnabled, $depositGateEnabled,
                 $isActive, $id]
            );
            $botId = $id;
        } else {
            // Create new bot
            $db->exec(
                'INSERT INTO bots (name, token, webapp_url, linked_channel, linked_channel_id, admin_group_id, support_link, referral_url_template, postback_secret, access_password,
                 channel_gate_enabled, reg_gate_enabled, password_gate_enabled, deposit_gate_enabled,
                 is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$name, $botToken, $webappUrl, $linkedChannel, $linkedChannelId, $adminGroupId, $supportLink, $referralUrlTemplate, $postbackSecret, $accessPassword,
                 $channelGateEnabled, $regGateEnabled, $passwordGateEnabled, $depositGateEnabled,
                 $isActive]
            );
            $botId = (int) $db->exec('SELECT LAST_INSERT_ID() AS id')[0]['id'];
        }

        // Auto-fill webapp_url from postback_base_url if the field was left empty.
        $baseUrl = SettingsService::get($db, 'postback_base_url');
        if ($baseUrl) {
            $autoWebappUrl = rtrim($baseUrl, '/') . '/app/' . $botId . '/';
            $db->exec(
                'UPDATE bots SET webapp_url = ? WHERE id = ? AND (webapp_url IS NULL OR webapp_url = "")',
                [$autoWebappUrl, $botId]
            );
        }

        $f3->reroute('/admin/bots');
    }
}
