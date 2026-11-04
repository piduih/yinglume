<?php
/**
 * YingLume Finance — Middleware Runner
 *
 * Resolves middleware names to concrete checks and executes them before
 * the router dispatches to a controller. Middleware may abort or redirect
 * the request; they never return a value — control passes through or stops.
 *
 * Built-in middleware:
 *   'auth'       — Require an authenticated session; redirect to /login otherwise.
 *   'guest'      — Redirect authenticated users away (login/register pages).
 *   'role:owner' — Require the user to hold a specific role (or any in a list).
 *   'csrf'       — Validate the CSRF token on POST requests.
 *   'verified'   — Require the user's email to be verified.
 *
 * @package YingLume\Core
 */

declare(strict_types=1);

namespace App\Core;

class Middleware
{
    /**
     * Resolve and execute a middleware by name.
     *
     * Middleware names may carry a colon-separated parameter, e.g. 'role:admin'.
     *
     * @param  string $name  Middleware name, optionally with parameter.
     */
    public static function run(string $name): void
    {
        $param = null;

        if (str_contains($name, ':')) {
            [$name, $param] = explode(':', $name, 2);
        }

        match ($name) {
            'auth'     => static::auth(),
            'guest'    => static::guest(),
            'role'     => static::role($param ?? ''),
            'csrf'     => static::csrf(),
            'verified' => static::verified(),
            default    => throw new \InvalidArgumentException("Unknown middleware: '{$name}'"),
        };
    }

    // ── Middleware Implementations ────────────────────────────────────────────

    /**
     * Require an authenticated session.
     * On failure: redirect to /login with an intended-URL flash message.
     */
    private static function auth(): void
    {
        if (!Auth::check()) {
            flash('intended', $_SERVER['REQUEST_URI'] ?? '/app/dashboard');
            flash('error', 'Please log in to continue.');
            Response::redirect('/login');
        }
    }

    /**
     * Redirect authenticated users away (for login / register pages).
     * On failure (already logged in): redirect to /app/dashboard.
     */
    private static function guest(): void
    {
        if (Auth::check()) {
            Response::redirect('/app/dashboard');
        }
    }

    /**
     * Require the authenticated user to hold a specific role (or one of a
     * comma-separated list of roles).
     *
     * @param  string $roles  Single role or comma-separated list, e.g. 'owner,admin'
     */
    private static function role(string $roles): void
    {
        static::auth(); // implicitly require authentication first

        $allowed = array_map('trim', explode(',', $roles));

        if (!Auth::hasRole($allowed)) {
            Response::abort(403, 'You do not have permission to access this page.');
        }
    }

    /**
     * Validate the CSRF token on POST requests.
     * GET requests are always allowed through.
     */
    private static function csrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $token = $_POST[CSRF::FIELD_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!CSRF::validate($token)) {
            Response::abort(419, 'CSRF token mismatch. Please refresh the page and try again.');
        }
    }

    /**
     * Require the authenticated user's email address to be verified.
     */
    private static function verified(): void
    {
        static::auth();

        $user = Auth::user();
        if (empty($user['email_verified_at'])) {
            flash('error', 'Please verify your email address before continuing.');
            Response::redirect('/verify-email');
        }
    }
}
