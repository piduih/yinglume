<?php
/**
 * YingLume Finance — Application Configuration
 *
 * Parses the .env file and defines application-wide constants.
 * Sets timezone and error reporting according to APP_DEBUG.
 *
 * @package YingLume\Config
 */

declare(strict_types=1);

// ── Load .env ────────────────────────────────────────────────────────────────
(static function (): void {
    $envFile = BASE_PATH . '/.env';

    if (!file_exists($envFile)) {
        // Fall back to .env.example so the app doesn't hard-crash on first run
        $envFile = BASE_PATH . '/.env.example';
        if (!file_exists($envFile)) {
            return;
        }
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Must contain '='
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip surrounding quotes
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Don't overwrite already-set env vars (allows server-level overrides)
        if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
})();

// ── Helper: read env with default ────────────────────────────────────────────
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $val = getenv($key);
        if ($val === false) {
            return $default;
        }
        return match (strtolower($val)) {
            'true',  '(true)'  => true,
            'false', '(false)' => false,
            'null',  '(null)'  => null,
            'empty', '(empty)' => '',
            default            => $val,
        };
    }
}

// ── Application constants ─────────────────────────────────────────────────────
defined('APP_NAME')     || define('APP_NAME',     env('APP_NAME',     'YingLume Finance'));
defined('APP_URL')      || define('APP_URL',       rtrim((string) env('APP_URL', 'http://localhost/yinglume'), '/'));
defined('APP_ENV')      || define('APP_ENV',       env('APP_ENV',      'production'));
defined('APP_DEBUG')    || define('APP_DEBUG',     (bool) env('APP_DEBUG', false));
defined('APP_SECRET')   || define('APP_SECRET',    (string) env('APP_SECRET', 'changeme_32_chars_minimum_key!!'));
defined('TIMEZONE')     || define('TIMEZONE',      env('TIMEZONE',      'Asia/Kuala_Lumpur'));
defined('DEFAULT_CURRENCY') || define('DEFAULT_CURRENCY', env('DEFAULT_CURRENCY', 'MYR'));
defined('DEFAULT_LANG') || define('DEFAULT_LANG',  env('DEFAULT_LANG',  'en'));

// ── Database constants ────────────────────────────────────────────────────────
defined('DB_HOST') || define('DB_HOST', env('DB_HOST', 'localhost'));
defined('DB_PORT') || define('DB_PORT', (int) env('DB_PORT', 3306));
defined('DB_NAME') || define('DB_NAME', env('DB_NAME', 'yinglume_finance'));
defined('DB_USER') || define('DB_USER', env('DB_USER', 'root'));
defined('DB_PASS') || define('DB_PASS', env('DB_PASS', ''));

// ── Mail constants ────────────────────────────────────────────────────────────
defined('MAIL_HOST')      || define('MAIL_HOST',      env('MAIL_HOST',      'localhost'));
defined('MAIL_PORT')      || define('MAIL_PORT',      (int) env('MAIL_PORT', 25));
defined('MAIL_USER')      || define('MAIL_USER',      env('MAIL_USER',      ''));
defined('MAIL_PASS')      || define('MAIL_PASS',      env('MAIL_PASS',      ''));
defined('MAIL_FROM')      || define('MAIL_FROM',      env('MAIL_FROM',      'noreply@yinglume.com'));
defined('MAIL_FROM_NAME') || define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'YingLume Finance'));

// ── Timezone ─────────────────────────────────────────────────────────────────
date_default_timezone_set(TIMEZONE);

// ── Error Reporting ──────────────────────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// Always log errors to file
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php_error_' . date('Y-m') . '.log');
