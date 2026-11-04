<?php
/**
 * YingLume Finance — Database Singleton
 *
 * Provides a single PDO connection for the entire request lifecycle.
 * All queries go through prepared statements — raw SQL concatenation is never used.
 *
 * @package YingLume\Core
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    /** @var Database|null Singleton instance */
    private static ?Database $instance = null;

    /** @var PDO The underlying PDO connection */
    private PDO $pdo;

    /** @var string Path to the error log file */
    private string $logFile;

    // ── Constructor ──────────────────────────────────────────────────────────

    /**
     * Private constructor — use getInstance().
     *
     * @throws \RuntimeException if the connection cannot be established.
     */
    private function __construct()
    {
        $this->logFile = STORAGE_PATH . '/logs/db_' . date('Y-m-d') . '.log';

        $config = require CONFIG_PATH . '/database.php';

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $e) {
            $this->logError('Connection failed: ' . $e->getMessage());
            throw new \RuntimeException('Database connection failed. Please check your configuration.');
        }
    }

    /** Prevent cloning of the singleton. */
    private function __clone() {}

    // ── Singleton Access ─────────────────────────────────────────────────────

    /**
     * Returns the single Database instance, creating it if necessary.
     */
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    // ── Core Query Execution ─────────────────────────────────────────────────

    /**
     * Prepare and execute a parameterised SQL statement.
     *
     * @param  string  $sql    Parameterised SQL.
     * @param  array   $params Bound parameters (positional or named).
     * @return PDOStatement    Executed statement ready for fetching.
     * @throws \RuntimeException on PDO error.
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError("Query failed: {$e->getMessage()} | SQL: {$sql}");
            throw new \RuntimeException('A database error occurred. Please try again.');
        }
    }

    // ── Convenience Read Methods ─────────────────────────────────────────────

    /**
     * Execute a SELECT and return all matching rows.
     *
     * @param  string $sql
     * @param  array  $params
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->select($sql, $params);
    }

    /**
     * Execute a SELECT and return the first matching row, or null.
     *
     * @param  string $sql
     * @param  array  $params
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row !== false ? $row : null;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        return $this->selectOne($sql, $params);
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    // ── Convenience Write Methods ────────────────────────────────────────────

    /**
     * Execute an INSERT statement and return the new row's auto-increment ID.
     *
     * @param  string $sql
     * @param  array  $params
     * @return string Last inserted ID (string to match PDO::lastInsertId return type).
     */
    public function insert(string $sql, array $params = []): string
    {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    /**
     * Execute an UPDATE statement and return the number of affected rows.
     *
     * @param  string $sql
     * @param  array  $params
     * @return int
     */
    public function update(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Execute a DELETE statement and return the number of affected rows.
     *
     * @param  string $sql
     * @param  array  $params
     * @return int
     */
    public function delete(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    // ── Transaction Helpers ──────────────────────────────────────────────────

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Roll back the current transaction.
     */
    public function rollback(): bool
    {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }
        return false;
    }

    /**
     * Return the last auto-increment ID generated by an INSERT.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Expose the raw PDO instance (use sparingly).
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    /**
     * Write a timestamped error entry to the daily DB log.
     */
    private function logError(string $message): void
    {
        $entry = sprintf("[%s] [DATABASE] %s\n", date('Y-m-d H:i:s'), $message);
        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
