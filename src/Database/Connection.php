<?php

/**
 * Database Connection Manager
 * 
 * Handles MySQL connections using PDO with prepared statements by default.
 * Implements connection retry logic (up to 3 attempts) for transient failures.
 * 
 * Requirements: 1.2, 5.1, 5.5
 */

declare(strict_types=1);

namespace Framework\Database;

use PDO;
use PDOException;
use PDOStatement;

class Connection
{
    private ?PDO $pdo = null;
    private array $config;
    private int $maxRetries;
    private int $retryDelay;
    
    /**
     * Create a new database connection instance
     *
     * @param array $config Database configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->maxRetries = $config['retry']['attempts'] ?? 3;
        $this->retryDelay = $config['retry']['delay'] ?? 100;
    }
    
    /**
     * Get the PDO connection instance
     * 
     * Establishes connection on first access (lazy loading).
     * Implements retry logic for transient connection failures.
     *
     * @return PDO
     * @throws PDOException If connection fails after all retries
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->createConnection();
        }
        
        return $this->pdo;
    }
    
    /**
     * Create a new PDO connection with retry logic
     *
     * @return PDO
     * @throws PDOException If connection fails after all retries
     */
    private function createConnection(): PDO
    {
        $attempts = 0;
        $lastException = null;
        
        while ($attempts < $this->maxRetries) {
            try {
                $attempts++;
                return $this->attemptConnection();
            } catch (PDOException $e) {
                $lastException = $e;
                
                // Don't retry if this is the last attempt
                if ($attempts >= $this->maxRetries) {
                    break;
                }
                
                // Wait before retrying (convert milliseconds to microseconds)
                usleep($this->retryDelay * 1000);
            }
        }
        
        // All retries failed, throw the last exception
        throw new PDOException(
            "Failed to connect to database after {$this->maxRetries} attempts: " . 
            $lastException->getMessage(),
            (int) $lastException->getCode(),
            $lastException
        );
    }
    
    /**
     * Attempt to establish a database connection
     *
     * @return PDO
     * @throws PDOException If connection fails
     */
    private function attemptConnection(): PDO
    {
        $connection = $this->config['connections'][$this->config['default']];
        
        // Build DSN based on driver
        if ($connection['driver'] === 'sqlite') {
            $dsn = sprintf('sqlite:%s', $connection['database']);
            $pdo = new PDO($dsn, null, null, $connection['options']);
        } else {
            // MySQL and other drivers
            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $connection['driver'],
                $connection['host'],
                $connection['port'],
                $connection['database'],
                $connection['charset']
            );
            
            $pdo = new PDO(
                $dsn,
                $connection['username'],
                $connection['password'],
                $connection['options']
            );
            
            // Set collation if specified (MySQL only)
            if (!empty($connection['collation'])) {
                $pdo->exec("SET NAMES '{$connection['charset']}' COLLATE '{$connection['collation']}'");
            }
        }
        
        return $pdo;
    }
    
    /**
     * Execute a query with prepared statement
     * 
     * All queries use prepared statements by default for security.
     *
     * @param string $query SQL query with placeholders
     * @param array $bindings Parameter bindings
     * @return PDOStatement
     * @throws PDOException If query execution fails
     */
    public function query(string $query, array $bindings = []): PDOStatement
    {
        $statement = $this->getPdo()->prepare($query);
        $statement->execute($bindings);
        
        return $statement;
    }
    
    /**
     * Execute a statement and return affected rows
     *
     * @param string $query SQL query with placeholders
     * @param array $bindings Parameter bindings
     * @return int Number of affected rows
     * @throws PDOException If execution fails
     */
    public function execute(string $query, array $bindings = []): int
    {
        $statement = $this->query($query, $bindings);
        return $statement->rowCount();
    }
    
    /**
     * Fetch all results from a query
     *
     * @param string $query SQL query with placeholders
     * @param array $bindings Parameter bindings
     * @return array
     * @throws PDOException If query fails
     */
    public function fetchAll(string $query, array $bindings = []): array
    {
        $statement = $this->query($query, $bindings);
        return $statement->fetchAll();
    }
    
    /**
     * Fetch a single row from a query
     *
     * @param string $query SQL query with placeholders
     * @param array $bindings Parameter bindings
     * @return array|false
     * @throws PDOException If query fails
     */
    public function fetchOne(string $query, array $bindings = []): array|false
    {
        $statement = $this->query($query, $bindings);
        return $statement->fetch();
    }
    
    /**
     * Get the last inserted ID
     *
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->getPdo()->lastInsertId();
    }

    /**
     * Get the database driver name (mysql, sqlite, etc.)
     */
    public function getDriver(): string
    {
        return $this->config['connections'][$this->config['default']]['driver'] ?? 'mysql';
    }
    
    /**
     * Begin a database transaction
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }
    
    /**
     * Commit the current transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }
    
    /**
     * Rollback the current transaction
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }
    
    /**
     * Check if currently in a transaction
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->getPdo()->inTransaction();
    }
    
    /**
     * Execute a callback within a database transaction
     * 
     * Automatically rolls back the transaction if an exception is thrown.
     * If already in a transaction, executes the callback without starting a new one.
     * Requirements: 5.6
     *
     * @param callable $callback The callback to execute within the transaction
     * @return mixed The return value of the callback
     * @throws \Throwable If the callback throws an exception
     */
    public function transaction(callable $callback): mixed
    {
        // If already in a transaction, just execute the callback
        $alreadyInTransaction = $this->inTransaction();
        
        if (!$alreadyInTransaction) {
            $this->beginTransaction();
        }
        
        try {
            $result = $callback($this);
            
            if (!$alreadyInTransaction) {
                $this->commit();
            }
            
            return $result;
        } catch (\Throwable $e) {
            if (!$alreadyInTransaction) {
                $this->rollback();
            }
            throw $e;
        }
    }
    
    /**
     * Disconnect from the database
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }
    
    /**
     * Reconnect to the database
     *
     * @return void
     * @throws PDOException If reconnection fails
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->getPdo();
    }
}
