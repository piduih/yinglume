<?php
/**
 * YingLume Finance — PSR-4-style Autoloader
 *
 * Maps top-level namespaces to filesystem directories without requiring
 * Composer. Register once in the front controller via:
 *
 *   $autoloader = new \App\Core\Autoloader();
 *   $autoloader->register();
 *
 * Namespace → directory mappings:
 *   App\     → <BASE_PATH>/app/
 *   Config\  → <BASE_PATH>/config/
 *
 * @package YingLume\Core
 */

declare(strict_types=1);

namespace App\Core;

class Autoloader
{
    /**
     * @var array<string, string>  Namespace prefix → base directory map.
     */
    private array $prefixes = [];

    public function __construct()
    {
        // Default mappings — extend via addNamespace() as needed
        $this->addNamespace('App\\',    BASE_PATH . '/app/');
        $this->addNamespace('Config\\', BASE_PATH . '/config/');
    }

    // ── Registration ─────────────────────────────────────────────────────────

    /**
     * Register this autoloader with PHP's SPL autoload stack.
     *
     * @param  bool $prepend  Prepend to the stack (higher priority) if true.
     */
    public function register(bool $prepend = false): void
    {
        spl_autoload_register([$this, 'load'], true, $prepend);
    }

    /**
     * Add or replace a namespace prefix → base directory mapping.
     *
     * @param  string $prefix     Namespace prefix with trailing backslash, e.g. 'App\\'.
     * @param  string $baseDir    Absolute directory path with trailing slash.
     */
    public function addNamespace(string $prefix, string $baseDir): void
    {
        // Normalise
        $prefix  = rtrim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;

        $this->prefixes[$prefix] = $baseDir;
    }

    // ── Core Loader ───────────────────────────────────────────────────────────

    /**
     * Attempt to load a class by its fully-qualified class name.
     *
     * @param  string $class  Fully-qualified class name, e.g. 'App\Core\Router'.
     * @return bool   True if the file was found and required; false otherwise.
     */
    public function load(string $class): bool
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            // Strip the prefix and convert namespace separators to path separators
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }

        return false;
    }
}
