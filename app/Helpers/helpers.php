<?php
/**
 * YingLume Finance — Global Helper Functions
 *
 * Loaded once by the front controller and available everywhere.
 * No class required — these are plain global functions.
 *
 * @package YingLume\Helpers
 */

declare(strict_types=1);

// ── Output Sanitisation ──────────────────────────────────────────────────────

/**
 * Safely HTML-encode a string for output in HTML context.
 * Shorthand for htmlspecialchars() with safe defaults.
 *
 * @param  mixed  $str  Value to encode (non-strings are cast).
 * @return string
 */
function h(mixed $str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── URL Helpers ──────────────────────────────────────────────────────────────

/**
 * Build an absolute URL by appending a path to APP_URL.
 *
 * @param  string $path  e.g. '/app/dashboard'
 * @return string        e.g. 'http://localhost/yinglume/app/dashboard'
 */
function url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Build a URL for a public asset (CSS, JS, images …).
 *
 * @param  string $path  e.g. 'css/app.css'
 * @return string        e.g. 'http://localhost/yinglume/assets/css/app.css'
 */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Issue an HTTP redirect and terminate the script.
 * Convenience wrapper around Response::redirect().
 *
 * @param  string $url   Path or absolute URL.
 * @param  int    $code  HTTP status code.
 */
function redirect(string $url, int $code = 302): never
{
    \App\Core\Response::redirect($url, $code);
}

// ── CSRF Helpers ─────────────────────────────────────────────────────────────

/**
 * Return an HTML hidden input element with the CSRF token.
 */
function csrf_field(): string
{
    return \App\Core\CSRF::field();
}

/**
 * Return the raw CSRF token string.
 */
function csrf_token(): string
{
    return \App\Core\CSRF::getToken();
}

// ── Money & Date Formatting ──────────────────────────────────────────────────

/**
 * Format a numeric amount as a localised currency string.
 *
 * @param  float|string $amount
 * @param  string|null  $currency  Currency code (defaults to DEFAULT_CURRENCY).
 * @return string        e.g. 'MYR 1,234.56'
 */
function money(float|string $amount, ?string $currency = null): string
{
    $currency ??= defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'MYR';
    return $currency . ' ' . number_format((float) $amount, 2);
}

/**
 * Format a date/datetime string using PHP date() format tokens.
 *
 * @param  string|null $date    Date string parsable by strtotime(), or null.
 * @param  string      $format  PHP date format (default: 'd M Y').
 * @param  string      $default Returned when $date is null/invalid.
 * @return string
 */
function date_fmt(?string $date, string $format = 'd M Y', string $default = '—'): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return $default;
    }
    $ts = strtotime($date);
    return $ts !== false ? date($format, $ts) : $default;
}

// ── Flash Messages ────────────────────────────────────────────────────────────

/**
 * Store a flash message in the session.
 *
 * @param  string $key      Flash key (e.g. 'success', 'error', 'info').
 * @param  string $message  Message text.
 */
function flash(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

/**
 * Retrieve and immediately clear a flash message.
 *
 * @param  string $key
 * @param  string $default  Returned when no flash message exists.
 * @return string
 */
function flash_get(string $key, string $default = ''): string
{
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

/**
 * Check whether a flash message exists for the given key
 * WITHOUT consuming it.
 */
function has_flash(string $key): bool
{
    return isset($_SESSION['_flash'][$key]);
}

/**
 * Get a session value or return default.
 *
 * @param  string|null $key
 * @param  mixed       $default
 * @return mixed
 */
function session(?string $key = null, mixed $default = null): mixed
{
    if ($key === null) {
        return $_SESSION;
    }
    return $_SESSION[$key] ?? $default;
}


// ── Debugging ─────────────────────────────────────────────────────────────────

/**
 * Dump a value and die — only active when APP_DEBUG is true.
 * In production this is silently suppressed.
 *
 * @param  mixed ...$vars  One or more values to dump.
 */
function dd(mixed ...$vars): never
{
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:1rem;border-radius:4px;'
           . 'font-size:.85rem;overflow:auto;white-space:pre-wrap;">';
        foreach ($vars as $var) {
            echo h(print_r($var, true)) . "\n";
        }
        echo '</pre>';
    }
    exit;
}

// ── UUID ──────────────────────────────────────────────────────────────────────

/**
 * Generate a UUID v4 (random).
 *
 * @return string  e.g. '550e8400-e29b-41d4-a716-446655440000'
 */
function generate_uuid(): string
{
    $data    = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ── String Utilities ──────────────────────────────────────────────────────────

/**
 * Convert a string to a URL-friendly slug.
 *
 * @param  string $str
 * @return string  e.g. 'hello-world-123'
 */
function slugify(string $str): string
{
    // Transliterate accented characters
    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?: $str;
    $str = strtolower($str);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str) ?? $str;
    return trim($str, '-');
}

/**
 * Truncate a UTF-8 string to a maximum length, appending a suffix.
 *
 * @param  string $str
 * @param  int    $length   Maximum character count (including suffix).
 * @param  string $suffix   Appended when truncation occurs (default '…').
 * @return string
 */
function truncate(string $str, int $length, string $suffix = '…'): string
{
    if (mb_strlen($str, 'UTF-8') <= $length) {
        return $str;
    }
    return mb_substr($str, 0, $length - mb_strlen($suffix, 'UTF-8'), 'UTF-8') . $suffix;
}

/**
 * Return a human-readable file size string from bytes.
 *
 * @param  int $bytes
 * @return string  e.g. '1.23 MB'
 */
function human_filesize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i     = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Mask a string, revealing only the last N characters.
 * Useful for displaying partial account numbers.
 *
 * @param  string $str
 * @param  int    $visible   Number of trailing characters to reveal.
 * @param  string $maskChar  Character used for masking (default '*').
 * @return string
 */
function mask(string $str, int $visible = 4, string $maskChar = '*'): string
{
    $len    = mb_strlen($str, 'UTF-8');
    $masked = $len > $visible ? str_repeat($maskChar, $len - $visible) : '';
    return $masked . mb_substr($str, -$visible, null, 'UTF-8');
}

/**
 * Check whether the current page URI matches a pattern (for nav active states).
 *
 * @param  string $pattern  URI prefix or exact path.
 * @return bool
 */
function active(string $pattern): bool
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    return str_starts_with($uri, $pattern);
}
