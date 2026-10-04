<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private bool $connected = false;

    private function __construct()
    {
        $config = require __DIR__ . '/../config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            $this->connected = true;
        } catch (PDOException $e) {
            $this->connected = false;
            // Log connection error silently or throw depending on environment
            error_log('Database Connection Error: ' . $e->getMessage());
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->pdo !== null;
    }

    /**
     * Connect to MySQL server without specifying a database (useful for setup/migrations)
     */
    public static function connectServer(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $config['host'],
            $config['port'],
            $config['charset']
        );

        return new PDO($dsn, $config['username'], $config['password'], $config['options']);
    }

    /**
     * Execute a parameterized SELECT query and return all matching rows.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        if (!$this->isConnected()) {
            return [];
        }
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e) {
            error_log('Database fetchAll error: ' . $e->getMessage() . ' in SQL: ' . $sql);
            return [];
        }
    }

    /**
     * Execute a parameterized SELECT query and return a single row.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        if (!$this->isConnected()) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            error_log('Database fetchOne error: ' . $e->getMessage() . ' in SQL: ' . $sql);
            return null;
        }
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE parameterized query.
     */
    public function execute(string $sql, array $params = []): bool
    {
        if (!$this->isConnected()) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('Database execute error: ' . $e->getMessage() . ' in SQL: ' . $sql);
            return false;
        }
    }

    /**
     * Return the last inserted ID.
     */
    public function lastInsertId(?string $name = null): string
    {
        if (!$this->isConnected()) {
            return '0';
        }
        return $this->pdo->lastInsertId($name) ?: '0';
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->isConnected() && $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->isConnected() && $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollBack(): bool
    {
        return $this->isConnected() && $this->pdo->rollBack();
    }
}
