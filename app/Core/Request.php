<?php
/**
 * YingLume Finance — HTTP Request Wrapper
 *
 * Provides sanitised access to GET/POST data, uploaded files, and
 * the current HTTP context. Also includes a simple validation engine.
 *
 * @package YingLume\Core
 */

declare(strict_types=1);

namespace App\Core;

class Request
{
    // ── Parameter Access ─────────────────────────────────────────────────────

    /**
     * Return a sanitised GET parameter.
     *
     * @param  string $key
     * @param  mixed  $default  Returned when the key is absent.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($_GET[$key])) {
            return $default;
        }
        return $this->sanitize($_GET[$key]);
    }

    /**
     * Return a sanitised POST parameter.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        if (!isset($_POST[$key])) {
            return $default;
        }
        return $this->sanitize($_POST[$key]);
    }

    /**
     * Return all POST parameters as a sanitised associative array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $result = [];
        foreach ($_POST as $key => $value) {
            $result[$key] = $this->sanitize($value);
        }
        return $result;
    }

    /**
     * Return a raw (un-sanitised) POST parameter — use only when you need
     * to pass the raw value to password_hash() or similar functions.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function raw(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Return uploaded file information from $_FILES.
     *
     * @param  string $key
     * @return array<string,mixed>|null
     */
    public function file(string $key): ?array
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE
            ? $_FILES[$key]
            : null;
    }

    // ── Request Meta ─────────────────────────────────────────────────────────

    /**
     * Return the HTTP method of the current request (uppercase).
     * Respects the _method override for PUT/PATCH/DELETE from HTML forms.
     */
    public function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    /**
     * Return the current request URI (path only, no query string).
     */
    public function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        return $pos !== false ? substr($uri, 0, $pos) : $uri;
    }

    /**
     * Return the full URL including scheme, host, and URI.
     */
    public function fullUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $this->uri();
    }

    /**
     * Whether the current request uses the POST method.
     */
    public function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    /**
     * Whether the current request was made via XMLHttpRequest / fetch with
     * the standard X-Requested-With header.
     */
    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * Return the client IP address (with proxy header support).
     */
    public function ip(): string
    {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For can be a comma-separated list; take the first
                return trim(explode(',', $_SERVER[$header])[0]);
            }
        }
        return '0.0.0.0';
    }

    /**
     * Return the value of a request header (case-insensitive).
     */
    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    // ── Validation ───────────────────────────────────────────────────────────

    /**
     * Validate POST data against a set of rules.
     *
     * Supported rules (pipe-separated per field):
     *   required        — field must not be empty
     *   email           — must be a valid e-mail address
     *   min:N           — minimum string length / numeric value
     *   max:N           — maximum string length / numeric value
     *   numeric         — must be numeric
     *   integer         — must be an integer
     *   date            — must be a valid Y-m-d date
     *   confirmed       — field must match {field}_confirmation
     *   in:a,b,c        — value must be one of the listed options
     *   regex:/pattern/ — value must match the regex pattern
     *
     * @param  array<string, string>  $rules  e.g. ['email' => 'required|email', 'age' => 'required|integer|min:1']
     * @return array{valid: bool, errors: array<string, string>}
     */
    public function validate(array $rules): array
    {
        $errors = [];
        $data   = $this->all();

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value      = $data[$field] ?? null;
            $label      = ucwords(str_replace(['_', '.'], ' ', $field));

            foreach ($fieldRules as $rule) {
                $ruleParam = null;

                if (str_contains($rule, ':')) {
                    [$rule, $ruleParam] = explode(':', $rule, 2);
                }

                $error = $this->applyRule($rule, $ruleParam, $field, $label, $value, $data);

                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // Stop at first error per field
                }
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    /**
     * Sanitize a scalar or recursive array value.
     * Strips whitespace; does NOT HTML-encode (use h() in templates).
     *
     * @param  mixed $value
     * @return mixed
     */
    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }

        if (is_string($value)) {
            // Strip null bytes and trim
            return trim(str_replace("\0", '', $value));
        }

        return $value;
    }

    /**
     * Apply a single validation rule and return an error message or null.
     *
     * @param  string      $rule
     * @param  string|null $param
     * @param  string      $field
     * @param  string      $label   Human-readable field label for messages
     * @param  mixed       $value
     * @param  array       $data    All POST data (for cross-field rules like 'confirmed')
     * @return string|null  Error message, or null if the rule passes.
     */
    private function applyRule(
        string $rule,
        ?string $param,
        string $field,
        string $label,
        mixed $value,
        array $data
    ): ?string {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
                    return "{$label} is required.";
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "{$label} must be a valid email address.";
                }
                break;

            case 'min':
                if ($value !== null && $value !== '') {
                    if (is_numeric($value) && (float)$value < (float)$param) {
                        return "{$label} must be at least {$param}.";
                    } elseif (!is_numeric($value) && mb_strlen((string)$value) < (int)$param) {
                        return "{$label} must be at least {$param} characters.";
                    }
                }
                break;

            case 'max':
                if ($value !== null && $value !== '') {
                    if (is_numeric($value) && (float)$value > (float)$param) {
                        return "{$label} must not exceed {$param}.";
                    } elseif (!is_numeric($value) && mb_strlen((string)$value) > (int)$param) {
                        return "{$label} must not exceed {$param} characters.";
                    }
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    return "{$label} must be a numeric value.";
                }
                break;

            case 'integer':
                if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    return "{$label} must be an integer.";
                }
                break;

            case 'date':
                if ($value !== null && $value !== '') {
                    $d = \DateTime::createFromFormat('Y-m-d', (string)$value);
                    if (!$d || $d->format('Y-m-d') !== (string)$value) {
                        return "{$label} must be a valid date (YYYY-MM-DD).";
                    }
                }
                break;

            case 'confirmed':
                $confirmation = $data["{$field}_confirmation"] ?? null;
                if ($value !== $confirmation) {
                    return "{$label} confirmation does not match.";
                }
                break;

            case 'in':
                if ($value !== null && $value !== '') {
                    $options = array_map('trim', explode(',', $param ?? ''));
                    if (!in_array((string)$value, $options, true)) {
                        return "{$label} must be one of: " . implode(', ', $options) . '.';
                    }
                }
                break;

            case 'regex':
                if ($value !== null && $value !== '' && !preg_match((string)$param, (string)$value)) {
                    return "{$label} has an invalid format.";
                }
                break;
        }

        return null;
    }
}
