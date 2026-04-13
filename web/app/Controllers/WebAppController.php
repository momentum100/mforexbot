<?php

namespace App\Controllers;

/**
 * Serves the Telegram Mini Web App (signal generator) for a specific bot.
 * The page is a static-ish HTML/JS page that calls the API endpoints.
 */
class WebAppController
{
    /**
     * Serve the signal generator mini web app.
     * GET /app/{bot_id}
     */
    public function index(\Base $f3): void
    {
        $botId = (int) $f3->get('PARAMS.bot_id');

        // Verify bot exists
        $db = $f3->get('DB');
        $result = $db->exec('SELECT id, name FROM bots WHERE id = ?', [$botId]);

        if (empty($result)) {
            $f3->error(404, 'Bot not found');
            return;
        }

        $f3->set('bot_id', $botId);
        $f3->set('bot_name', $result[0]['name']);

        echo \Template::instance()->render('webapp/index.html');
    }
}
