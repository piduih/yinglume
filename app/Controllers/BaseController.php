<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\CSRF;
use App\Core\Database;

/**
 * BaseController
 * Abstract base class for all controllers.
 * Provides shared utilities: rendering, auth enforcement, validation, pagination, flash messages.
 */
abstract class BaseController
{
    protected Request  $request;
    protected Response $response;

    public function __construct()
    {
        $this->request  = new Request();
        $this->response = new Response();
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    /**
     * Render a view inside a layout.
     *
     * @param string $view   Dot-notation view path relative to views/ (e.g. 'accounts.index')
     * @param array  $data   Variables passed to the view
     * @param string $layout Layout name inside views/layouts/
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        // Make variables available in view scope
        extract($data, EXTR_SKIP);

        $viewPath   = VIEW_PATH . '/' . str_replace('.', '/', $view) . '.php';
        $layoutPath = VIEW_PATH . '/layouts/' . $layout . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$viewPath}");
        }

        // Buffer the inner view
        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout && file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            echo $content;
        }
    }

    /**
     * Send a JSON response and exit.
     */
    protected function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redirect to a URL and exit.
     */
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    // -------------------------------------------------------------------------
    // Authentication & Authorisation
    // -------------------------------------------------------------------------

    /**
     * Redirect to login if the user is not authenticated.
     */
    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            $this->flash('error', 'Please log in to continue.');
            $this->redirect('/login');
        }
    }

    /**
     * Require a specific role (or one of a list of roles).
     *
     * @param string|string[] $role
     */
    protected function requireRole(string|array $role): void
    {
        $this->requireAuth();
        $roles = (array) $role;
        if (!in_array(Auth::user()['role'] ?? '', $roles, true)) {
            http_response_code(403);
            $this->render('errors.403', [], 'main');
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // CSRF
    // -------------------------------------------------------------------------

    /**
     * Validate CSRF token on state-changing requests.
     * Aborts with 419 on failure.
     */
    protected function validateCSRF(): void
    {
        if (!CSRF::validate($this->input('_token', ''))) {
            http_response_code(419);
            $this->render('errors.419', [], 'main');
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // Input & Validation
    // -------------------------------------------------------------------------

    /**
     * Get a sanitized POST or GET value.
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (is_string($value)) {
            return trim($value);
        }
        return $value;
    }

    /**
     * Validate input against a simple rules array.
     * Rules format: ['field' => 'required|email|min:6|max:255|numeric']
     *
     * @return array<string, string>|false Returns associative errors array or false if valid
     */
    protected function validate(array $rules): array|false
    {
        $errors = [];

        foreach ($rules as $field => $ruleStr) {
            $ruleList = explode('|', $ruleStr);
            $value    = $this->input($field, '');
            $label    = ucwords(str_replace('_', ' ', $field));

            foreach ($ruleList as $rule) {
                [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);

                switch ($ruleName) {
                    case 'required':
                        if ($value === '' || $value === null) {
                            $errors[$field] = "{$label} is required.";
                        }
                        break;

                    case 'email':
                        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "{$label} must be a valid email address.";
                        }
                        break;

                    case 'min':
                        if (strlen((string) $value) < (int) $param) {
                            $errors[$field] = "{$label} must be at least {$param} characters.";
                        }
                        break;

                    case 'max':
                        if (strlen((string) $value) > (int) $param) {
                            $errors[$field] = "{$label} may not exceed {$param} characters.";
                        }
                        break;

                    case 'numeric':
                        if ($value !== '' && !is_numeric($value)) {
                            $errors[$field] = "{$label} must be a number.";
                        }
                        break;

                    case 'integer':
                        if ($value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $errors[$field] = "{$label} must be an integer.";
                        }
                        break;

                    case 'date':
                        if ($value !== '' && \DateTime::createFromFormat('Y-m-d', $value) === false) {
                            $errors[$field] = "{$label} must be a valid date (YYYY-MM-DD).";
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', (string) $param);
                        if ($value !== '' && !in_array($value, $allowed, true)) {
                            $errors[$field] = "{$label} has an invalid value.";
                        }
                        break;

                    case 'confirmed':
                        $confirm = $this->input($field . '_confirmation', '');
                        if ($value !== $confirm) {
                            $errors[$field] = "{$label} confirmation does not match.";
                        }
                        break;
                }

                // Stop checking other rules if this field already has an error
                if (isset($errors[$field])) {
                    break;
                }
            }
        }

        return empty($errors) ? false : $errors;
    }

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------

    /**
     * Run a paginated query.
     *
     * @param string $query     SQL query with optional :limit and :offset placeholders,
     *                          or a plain query that will have LIMIT/OFFSET appended.
     * @param array  $params    Bound parameters (excluding :limit / :offset)
     * @param int    $perPage   Rows per page
     * @return array{data: array, total: int, page: int, perPage: int, lastPage: int}
     */
    protected function paginate(string $query, array $params = [], int $perPage = 20): array
    {
        $db   = Database::getInstance();
        $page = max(1, (int) ($this->input('page', 1)));

        // Build a count query by wrapping the main query
        $countQuery = "SELECT COUNT(*) AS total FROM ({$query}) AS _pag";
        $total      = (int) $db->fetchOne($countQuery, $params)['total'];

        $offset = ($page - 1) * $perPage;
        $data   = $db->fetchAll($query . ' LIMIT :limit OFFSET :offset', array_merge($params, [
            ':limit'  => $perPage,
            ':offset' => $offset,
        ]));

        return [
            'data'     => $data,
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => (int) ceil($total / max(1, $perPage)),
        ];
    }

    // -------------------------------------------------------------------------
    // Flash Messages
    // -------------------------------------------------------------------------

    /**
     * Store a flash message in the session.
     */
    protected function flash(string $key, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Retrieve and clear a flash message.
     */
    protected function getFlash(string $key): ?string
    {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $message;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get current authenticated user array.
     */
    protected function user(): ?array
    {
        return Auth::user();
    }

    /**
     * Get current tenant ID.
     */
    protected function tenantId(): int
    {
        return Auth::tenantId();
    }
}
