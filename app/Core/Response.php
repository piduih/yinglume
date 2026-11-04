<?php
/**
 * YingLume Finance — HTTP Response Helper
 *
 * Provides static helpers for redirecting, returning JSON, rendering views,
 * and aborting requests with standard error pages.
 *
 * @package YingLume\Core
 */

declare(strict_types=1);

namespace App\Core;

class Response
{
    // ── Redirect ─────────────────────────────────────────────────────────────

    /**
     * Issue an HTTP redirect and terminate the script.
     *
     * @param  string $url   Absolute URL or path relative to APP_URL.
     * @param  int    $code  HTTP status code (301, 302, 303 …).
     */
    public static function redirect(string $url, int $code = 302): never
    {
        // If the URL is a relative path, prepend APP_URL
        if (!preg_match('#^https?://#i', $url)) {
            $url = rtrim(APP_URL, '/') . '/' . ltrim($url, '/');
        }

        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Redirect back to the previous page (Referer), or to a fallback URL.
     *
     * @param  string $fallback  URL to use when no Referer header is present.
     */
    public static function back(string $fallback = '/'): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        static::redirect($referer !== '' ? $referer : $fallback);
    }

    // ── JSON ─────────────────────────────────────────────────────────────────

    /**
     * Send a JSON response and terminate.
     *
     * @param  mixed  $data  Data to encode.
     * @param  int    $code  HTTP status code.
     * @param  int    $flags json_encode flags (default: JSON_UNESCAPED_UNICODE).
     */
    public static function json(mixed $data, int $code = 200, int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, $flags);
        exit;
    }

    // ── View Rendering ────────────────────────────────────────────────────────

    /**
     * Render a PHP view template, passing variables into its scope.
     *
     * Template path resolution:
     *   'dashboard'          → <VIEW_PATH>/dashboard.php
     *   'auth/login'         → <VIEW_PATH>/auth/login.php
     *   'invoices/create'    → <VIEW_PATH>/invoices/create.php
     *
     * @param  string              $template  Dot-notation or slash-separated template path.
     * @param  array<string,mixed> $data      Variables extracted into template scope.
     * @param  int                 $code      HTTP status code.
     */
    public static function view(string $template, array $data = [], int $code = 200): void
    {
        http_response_code($code);

        // Normalise dots to slashes
        $template = str_replace('.', '/', $template);
        $file     = VIEW_PATH . '/' . ltrim($template, '/') . '.php';

        if (!file_exists($file)) {
            static::abort(500, "View '{$template}' not found at: {$file}");
        }

        // Expose data variables to the template
        extract($data, EXTR_SKIP);

        // Convenience shorthand available in every view
        $request = new Request();
        $auth    = null;
        if (Auth::check()) {
            $auth = Auth::user();
        }

        require $file;
    }

    // ── Error Pages ───────────────────────────────────────────────────────────

    /**
     * Send an HTTP error response and terminate.
     *
     * Looks for a view at errors/{code}.php; falls back to a plain message.
     *
     * @param  int    $code     HTTP status code.
     * @param  string $message  Human-readable error description.
     */
    public static function abort(int $code, string $message = ''): never
    {
        http_response_code($code);

        $viewFile = VIEW_PATH . '/errors/' . $code . '.php';

        if (file_exists($viewFile)) {
            $errorMessage = $message;
            require $viewFile;
        } else {
            $titles = [
                400 => 'Bad Request',
                401 => 'Unauthorised',
                403 => 'Forbidden',
                404 => 'Not Found',
                500 => 'Internal Server Error',
            ];
            $title = $titles[$code] ?? 'Error';
            $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

            echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\">
                  <title>{$code} — {$title}</title>
                  <style>body{font-family:sans-serif;padding:3rem;color:#333}
                  h1{font-size:2rem}p{color:#666}</style></head><body>
                  <h1>{$code} — {$title}</h1>
                  <p>{$safeMsg}</p>
                  <p><a href=\"" . htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') . "\">Return to Home</a></p>
                  </body></html>";
        }

        exit;
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    /**
     * Set an arbitrary response header (call before any output).
     */
    public static function header(string $name, string $value): void
    {
        header("{$name}: {$value}");
    }

    /**
     * Set the HTTP response code.
     */
    public static function status(int $code): void
    {
        http_response_code($code);
    }
}
