<?php
/**
 * YingLume Finance — Front Controller
 *
 * Entry point for all HTTP requests. Loads configuration, registers the
 * autoloader, starts the session, initialises the router and dispatches
 * the current request.
 *
 * @package YingLume
 */

declare(strict_types=1);

// Serve static assets directly when using PHP built-in CLI server
if (php_sapi_name() === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

// ── Timing (dev profiling) ───────────────────────────────────────────────────
define('YINGLUME_START', microtime(true));

// ── Base paths ───────────────────────────────────────────────────────────────
define('BASE_PATH',    dirname(__DIR__));
define('APP_PATH',     BASE_PATH . '/app');
define('CONFIG_PATH',  BASE_PATH . '/config');
define('PUBLIC_PATH',  __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');
define('VIEW_PATH',    APP_PATH  . '/Views');

// ── Autoloader ───────────────────────────────────────────────────────────────
require_once APP_PATH . '/Core/Autoloader.php';

$autoloader = new \App\Core\Autoloader();
$autoloader->register();

// ── Helpers ──────────────────────────────────────────────────────────────────
require_once APP_PATH . '/Helpers/helpers.php';

// ── Configuration ────────────────────────────────────────────────────────────
require_once CONFIG_PATH . '/config.php';

// ── Session ──────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookieParams['path'],
        'domain'   => $cookieParams['domain'],
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('ylsession');
    session_start();
}

// ── CSRF token bootstrap ─────────────────────────────────────────────────────
\App\Core\CSRF::generateToken();

// ── Router ───────────────────────────────────────────────────────────────────
$router = new \App\Core\Router();

require_once PUBLIC_PATH . '/routes.php';

// ── Dispatch ─────────────────────────────────────────────────────────────────
try {
    $router->dispatch();
} catch (\Throwable $e) {
    // Log the exception
    $logFile = STORAGE_PATH . '/logs/error_' . date('Y-m-d') . '.log';
    $entry   = sprintf(
        "[%s] %s in %s:%d\nStack trace:\n%s\n\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo '<pre>' . htmlspecialchars($entry, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        http_response_code(500);
        echo '<h1>500 — Internal Server Error</h1><p>Something went wrong. Please try again later.</p>';
    }
}
