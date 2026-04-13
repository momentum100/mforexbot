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

// Load .env from project root
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
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');

// ---------------------------------------------------------------------------
// Security Headers (all responses)
// ---------------------------------------------------------------------------

$f3->set('ONREROUTE', function ($url, $permanent) use ($f3) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
});

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// ---------------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------------

// --- Admin Panel ---
$f3->route('GET /admin', 'App\Controllers\AdminController->redirectToBots');
$f3->route('GET /admin/login', 'App\Controllers\AuthController->loginForm');
$f3->route('POST /admin/login', 'App\Controllers\AuthController->login');
$f3->route('GET /admin/logout', 'App\Controllers\AuthController->logout');

$f3->route('GET /admin/bots', 'App\Controllers\AdminController->bots');
$f3->route('POST /admin/bots', 'App\Controllers\AdminController->saveBots');

$f3->route('GET /admin/translations', 'App\Controllers\AdminController->translations');
$f3->route('POST /admin/translations/save', 'App\Controllers\AdminController->saveTranslation');
$f3->route('POST /admin/translations/delete', 'App\Controllers\AdminController->deleteTranslation');

// --- Web App (Telegram Mini App) ---
$f3->route('GET /app/@bot_id', 'App\Controllers\WebAppController->index');

// --- Web App API ---
$f3->route('GET /app/@bot_id/api/translations', 'App\Controllers\ApiController->translations');
$f3->route('GET /app/@bot_id/api/pairs', 'App\Controllers\ApiController->pairs');
$f3->route('POST /app/@bot_id/api/signal', 'App\Controllers\ApiController->signal');

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
