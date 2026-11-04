<?php
/**
 * YingLume Finance — CSRF Protection
 *
 * Generates, stores, and validates synchronised-token CSRF tokens.
 * Tokens are stored in $_SESSION keyed by a constant session key.
 *
 * Usage:
 *   // In your form template:
 *   echo CSRF::field();
 *
 *   // In your POST handler (via Request::validate or manually):
 *   CSRF::validate($_POST['_token'] ?? '');
 *
 * @package YingLume\Core
 */

declare(strict_types=1);

namespace App\Core;

class CSRF
{
    /** Session key under which the token is stored. */
    private const SESSION_KEY = '_yl_csrf_token';

    /** HTML field name used in forms and AJAX headers. */
    public const FIELD_NAME = '_token';

    // ── Token Lifecycle ──────────────────────────────────────────────────────

    /**
     * Ensure a CSRF token exists in the session.
     * Called once per request from index.php — idempotent.
     */
    public static function generateToken(): void
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = self::createToken();
        }
    }

    /**
     * Return the current session CSRF token, generating one if absent.
     */
    public static function getToken(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = self::createToken();
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Validate a submitted token against the session token using a
     * timing-safe comparison to prevent timing attacks.
     *
     * @param  string $submittedToken  Token from the form field or AJAX header.
     * @return bool   True if valid.
     */
    public static function validate(string $submittedToken): bool
    {
        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';

        if ($sessionToken === '' || $submittedToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    /**
     * Regenerate the token (call after each sensitive POST action).
     */
    public static function regenerate(): void
    {
        $_SESSION[self::SESSION_KEY] = self::createToken();
    }

    // ── HTML Helpers ─────────────────────────────────────────────────────────

    /**
     * Return an HTML hidden input element containing the CSRF token.
     *
     * @return string  e.g. <input type="hidden" name="_token" value="...">
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars(self::FIELD_NAME, ENT_QUOTES, 'UTF-8');
        return "<input type=\"hidden\" name=\"{$name}\" value=\"{$token}\">";
    }

    /**
     * Return an HTML <meta> tag for use with AJAX (e.g. fetch/axios headers).
     *
     * @return string  e.g. <meta name="csrf-token" content="...">
     */
    public static function meta(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8');
        return "<meta name=\"csrf-token\" content=\"{$token}\">";
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    /**
     * Generate a cryptographically secure 64-character hex token.
     */
    private static function createToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
