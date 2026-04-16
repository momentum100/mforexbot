<?php
/**
 * M-Bot-Forex Web Application Bootstrap
 * Fat-Free Framework (F3) entry point
 */

// Composer autoload
require __DIR__ . '/../vendor/autoload.php';

$f3 = \Base::instance();

// ---------------------------------------------------------------------------
// Environment Configuration
// ---------------------------------------------------------------------------

// Load config from environment variables (Docker) or .env file (local dev)
foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $key) {
    $val = getenv($key);
    if ($val !== false) {
        $f3->set($key, $val);
    }
}

// Fallback: load .env from project root (local dev without Docker)
if (!$f3->get('DB_HOST')) {
    $envFile = __DIR__ . '/../../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $f3->set(trim($key), trim($value));
            }
        }
    }
}

// Database connection string for F3
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $f3->get('DB_HOST'),
    $f3->get('DB_PORT') ?: '3306',
    $f3->get('DB_NAME')
);

$db = new \DB\SQL($dsn, $f3->get('DB_USER'), $f3->get('DB_PASS'), [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    \PDO::ATTR_EMULATE_PREPARES => false,
]);
// F3's \DB\SQL wrapper ignores charset=utf8mb4 in DSN; force it so 4-byte
// chars (emoji flags in translations) don't come back as '??'.
$db->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
$f3->set('DB', $db);

// ---------------------------------------------------------------------------
// Framework Settings
// ---------------------------------------------------------------------------

$f3->set('DEBUG', 0);
$f3->set('UI', __DIR__ . '/../app/Views/');
$f3->set('TEMP', __DIR__ . '/../tmp/');
$f3->set('LOGS', __DIR__ . '/../tmp/logs/');
$f3->set('CACHE', false);
$f3->set('ENCODING', 'UTF-8');

// ---------------------------------------------------------------------------
// Session Configuration
// ---------------------------------------------------------------------------

ini_set('session.gc_maxlifetime', 1800); // 30 minutes
ini_set('session.cookie_httponly', 1);
// Only secure cookies over HTTPS (production); allow HTTP in local dev
ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');

// ---------------------------------------------------------------------------
// Security Headers (all responses)
// ---------------------------------------------------------------------------

$f3->set('ONREROUTE', function ($url, $permanent) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    if (PHP_SAPI !== 'cli') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $code = 303;
        } else {
            $code = $permanent ? 301 : 302;
        }
        header('Location: ' . $url, true, $code);
    }
    exit;
});

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// ---------------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------------

// --- Admin Panel ---
$f3->route('GET /admin', 'App\Controllers\AdminController->redirectToBots');
$f3->route('GET /admin/', 'App\Controllers\AdminController->redirectToBots');
$f3->route('GET /admin/login', 'App\Controllers\AuthController->loginForm');
$f3->route('POST /admin/login', 'App\Controllers\AuthController->login');
$f3->route('GET /admin/logout', 'App\Controllers\AuthController->logout');

$f3->route('GET /admin/bots', 'App\Controllers\AdminController->bots');
$f3->route('POST /admin/bots', 'App\Controllers\AdminController->saveBots');
$f3->route('POST /admin/bots/restart', 'App\Controllers\AdminController->restartBots');

$f3->route('GET /admin/translations', 'App\Controllers\AdminController->translations');
$f3->route('POST /admin/translations/save', 'App\Controllers\AdminController->saveTranslation');
$f3->route('POST /admin/translations/delete', 'App\Controllers\AdminController->deleteTranslation');

$f3->route('GET /admin/settings', 'App\Controllers\AdminController->settings');
$f3->route('POST /admin/settings', 'App\Controllers\AdminController->saveSettings');

$f3->route('GET /admin/users', 'App\Controllers\AdminController->users');
$f3->route('GET /admin/postbacks', 'App\Controllers\AdminController->postbacks');
$f3->route('POST /admin/users/@id/delete', 'App\Controllers\AdminController->deleteUser');
$f3->route('POST /admin/users/@id/toggle-admin', 'App\Controllers\AdminController->toggleUserAdmin');

// --- Web App (Telegram Mini App) ---
$f3->route('GET /app/@bot_id', 'App\Controllers\WebAppController->index');

// --- Web App API ---
$f3->route('GET /app/@bot_id/api/translations', 'App\Controllers\ApiController->translations');
$f3->route('GET /app/@bot_id/api/pairs', 'App\Controllers\ApiController->pairs');
$f3->route('GET /app/@bot_id/api/market-status', 'App\Controllers\ApiController->marketStatus');
$f3->route('POST /app/@bot_id/api/signal', 'App\Controllers\ApiController->signal');
$f3->route('GET /app/@bot_id/api/check-access', 'App\Controllers\ApiController->checkAccess');

// --- Postback Route ---
$f3->route('GET /postback', 'App\Controllers\ApiController->postback');

// ---------------------------------------------------------------------------
// Error Handler
// ---------------------------------------------------------------------------

$f3->set('ONERROR', function ($f3) {
    $code = $f3->get('ERROR.code');
    if ($f3->get('AJAX')) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $f3->get('ERROR.text')]);
    } else {
        echo '<h1>Error ' . $code . '</h1>';
        echo '<p>' . htmlspecialchars($f3->get('ERROR.text')) . '</p>';
    }
});

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

$f3->run();
