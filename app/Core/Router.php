<?php
/**
 * YingLume Finance — HTTP Router
 *
 * Lightweight front-controller router supporting:
 *  - GET / POST route registration
 *  - Named routes
 *  - Route groups (prefix + middleware stack)
 *  - Wildcard path parameters  (/invoices/{id})
 *  - Custom 404 handler
 *
 * @package YingLume\Core
 */

declare(strict_types=1);

namespace App\Core;

class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:string|callable, name:string|null, middleware:string[]}> */
    private array $routes = [];

    /** @var array<string, string> Named route map  name => pattern */
    private array $namedRoutes = [];

    /** @var callable|null */
    private $notFoundHandler = null;

    /** @var string[] Active group middleware stack */
    private array $groupMiddleware = [];

    /** @var string Active group prefix */
    private string $groupPrefix = '';

    // ── Route Registration ───────────────────────────────────────────────────

    /**
     * Register a GET route.
     *
     * @param  string          $path       URI pattern, e.g. '/invoices/{id}'
     * @param  string|callable $handler    'ControllerClass@method' or a Closure
     * @param  string|null     $name       Optional named route identifier
     * @return static
     */
    public function get(string $path, string|callable $handler, ?string $name = null): static
    {
        return $this->addRoute('GET', $path, $handler, $name);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, string|callable $handler, ?string $name = null): static
    {
        return $this->addRoute('POST', $path, $handler, $name);
    }

    /**
     * Register a route for any HTTP method.
     */
    public function any(string $path, string|callable $handler, ?string $name = null): static
    {
        $this->addRoute('GET',  $path, $handler, $name);
        $this->addRoute('POST', $path, $handler, $name);
        return $this;
    }

    /**
     * Group routes under a shared prefix and/or middleware.
     *
     * @param array{prefix?:string, middleware?:string|string[]} $options
     * @param callable $callback  Receives $this (the router) so routes can be added.
     */
    public function group(array $options, callable $callback): void
    {
        // Save current group context
        $previousPrefix     = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        // Apply group options
        $this->groupPrefix .= $options['prefix'] ?? '';

        $middleware = $options['middleware'] ?? [];
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        // Execute the group callback
        $callback($this);

        // Restore previous context
        $this->groupPrefix     = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Set a custom 404 handler.
     */
    public function setNotFound(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    // ── Dispatch ─────────────────────────────────────────────────────────────

    /**
     * Match the current request and dispatch to the appropriate handler.
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Support _method override for PUT/PATCH/DELETE from forms
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $uri = $this->currentUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            if ($this->matchPattern($route['pattern'], $uri, $params)) {
                // Run middleware
                foreach ($route['middleware'] as $mw) {
                    Middleware::run($mw);
                }

                // Dispatch handler
                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        // No route matched
        $this->handle404();
    }

    // ── Named Route URL Generation ───────────────────────────────────────────

    /**
     * Generate a URL for a named route.
     *
     * @param  string              $name    Route name
     * @param  array<string,mixed> $params  Parameter substitutions for {param} tokens
     * @return string              Full URL
     * @throws \InvalidArgumentException if the named route is not found.
     */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Named route '{$name}' not found.");
        }

        $pattern = $this->namedRoutes[$name];

        foreach ($params as $key => $value) {
            $pattern = str_replace('{' . $key . '}', (string) $value, $pattern);
        }

        return APP_URL . $pattern;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /**
     * Add a route entry to the registry.
     */
    private function addRoute(string $method, string $path, string|callable $handler, ?string $name): static
    {
        $pattern = $this->groupPrefix . $path;

        $entry = [
            'method'     => $method,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'name'       => $name,
            'middleware' => $this->groupMiddleware,
        ];

        $this->routes[] = $entry;

        if ($name !== null) {
            $this->namedRoutes[$name] = $pattern;
        }

        return $this;
    }

    /**
     * Match a URI against a route pattern.
     * Converts {param} tokens into named regex captures.
     *
     * @param  string $pattern  Route pattern, e.g. '/invoices/{id}/edit'
     * @param  string $uri      Actual request URI
     * @param  array  &$params  Captured named parameters
     * @return bool
     */
    private function matchPattern(string $pattern, string $uri, array &$params): bool
    {
        // Convert {param} → named capture group (?P<param>[^/]+)
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#u';

        if (preg_match($regex, $uri, $matches)) {
            // Extract only named captures
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Return the current request URI, stripped of the APP_URL base path and query string.
     */
    private function currentUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Strip query string
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remove the APP_URL base path so routes are relative
        $basePath = parse_url(APP_URL, PHP_URL_PATH) ?? '';
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        return '/' . ltrim($uri, '/');
    }

    /**
     * Invoke the matched handler (Closure or 'Controller@method' string).
     *
     * @param string|callable $handler
     * @param array           $params  Route parameters
     */
    private function callHandler(string|callable $handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, [$params]);
            return;
        }

        // Parse 'ControllerClass@method' — class can be short (sans namespace) or FQN
        if (!str_contains($handler, '@')) {
            throw new \RuntimeException("Invalid handler format: '{$handler}'. Expected 'Class@method'.");
        }

        [$class, $method] = explode('@', $handler, 2);

        // If not fully qualified, prepend default controller namespace
        if (!str_starts_with($class, '\\') && !str_contains($class, '\\')) {
            $class = "\\App\\Controllers\\{$class}";
        }

        if (!class_exists($class)) {
            throw new \RuntimeException("Controller class '{$class}' not found.");
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Method '{$method}' not found on controller '{$class}'.");
        }

        if (!empty($params)) {
            $refMethod = new \ReflectionMethod($controller, $method);
            $methodParams = $refMethod->getParameters();
            if (count($methodParams) === 1 && $methodParams[0]->hasType() && $methodParams[0]->getType() instanceof \ReflectionNamedType && $methodParams[0]->getType()->getName() === 'array') {
                $controller->$method($params);
            } else {
                $controller->$method(...array_values($params));
            }
        } else {
            $controller->$method();
        }
    }

    /**
     * Trigger the 404 response.
     */
    private function handle404(): void
    {
        http_response_code(404);

        if ($this->notFoundHandler !== null) {
            call_user_func($this->notFoundHandler);
            return;
        }

        $viewPath = defined('VIEW_PATH') ? VIEW_PATH . '/errors/404.php' : '';
        if ($viewPath && file_exists($viewPath)) {
            $pageTitle = 'Page Not Found — YingLume';
            ob_start();
            require $viewPath;
            $content = ob_get_clean();

            $layoutPath = VIEW_PATH . '/layouts/' . (Auth::check() ? 'main.php' : 'landing.php');
            if (file_exists($layoutPath)) {
                $user = Auth::user();
                $tenant = [];
                $currentUri = $this->currentUri();
                require $layoutPath;
            } else {
                echo $content;
            }
            return;
        }

        // Default plain-text 404
        echo '<h1>404 — Page Not Found</h1>';
        echo '<p>The page you are looking for does not exist.</p>';
        echo '<p><a href="' . htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') . '">Return to Home</a></p>';
    }
}
