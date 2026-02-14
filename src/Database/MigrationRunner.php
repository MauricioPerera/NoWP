<?php

declare(strict_types=1);

namespace Framework\Database;

use RuntimeException;
use DirectoryIterator;

class MigrationRunner
{
    private Connection $connection;
    private string $migrationsPath;
    private string $migrationsTable = 'migrations';

    public function __construct(Connection $connection, string $migrationsPath)
    {
        $this->connection = $connection;
        $this->migrationsPath = rtrim($migrationsPath, '/');
    }

    public function createMigrationsTable(): void
    {
        $query = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT PRIMARY KEY AUTO_INCREMENT,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_batch (batch)
        )";

        $this->connection->execute($query);
    }

    public function run(): array
    {
        $this->createMigrationsTable();
        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();
        $executed = [];

        foreach ($pending as $migrationFile) {
            $migration = $this->loadMigration($migrationFile);

            try {
                $this->connection->transaction(function() use ($migration, $migrationFile, $batch) {
                    $migration->up();
                    $this->recordMigration($migrationFile, $batch);
                });

                $executed[] = $migrationFile;
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    "Migration failed: {$migrationFile}. Error: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return $executed;
    }

    public function rollback(): array
    {
        $lastBatch = $this->getLastBatchNumber();

        if ($lastBatch === null) {
            return [];
        }

        return $this->rollbackBatch($lastBatch);
    }

    public function rollbackBatch(int $batch): array
    {
        $migrations = $this->getMigrationsInBatch($batch);
        $rolledBack = [];

        foreach (array_reverse($migrations) as $migrationName) {
            $migration = $this->loadMigration($migrationName);

            try {
                $this->connection->transaction(function() use ($migration, $migrationName) {
                    $migration->down();
                    $this->removeMigrationRecord($migrationName);
                });

                $rolledBack[] = $migrationName;
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    "Rollback failed: {$migrationName}. Error: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return $rolledBack;
    }

    public function reset(): array
    {
        while ($this->getLastBatchNumber() !== null) {
            $this->rollback();
        }

        return $this->run();
    }

    public function status(): array
    {
        $this->createMigrationsTable();

        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();

        $status = [];

        foreach ($allMigrations as $migration) {
            $status[] = [
                'migration' => $migration,
                'executed' => in_array($migration, $executedMigrations),
            ];
        }

        return $status;
    }

    private function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();

        return array_diff($allMigrations, $executedMigrations);
    }

    private function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $migrations = [];
        $iterator = new DirectoryIterator($this->migrationsPath);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $migrations[] = $file->getBasename('.php');
            }
        }

        sort($migrations);
        return $migrations;
    }

    private function getExecutedMigrations(): array
    {
        try {
            $results = $this->connection->fetchAll(
                "SELECT migration FROM {$this->migrationsTable} ORDER BY id"
            );

            return array_column($results, 'migration');
        } catch (\PDOException $e) {
            return [];
        }
    }

    private function getMigrationsInBatch(int $batch): array
    {
        $results = $this->connection->fetchAll(
            "SELECT migration FROM {$this->migrationsTable} WHERE batch = ? ORDER BY id",
            [$batch]
        );

        return array_column($results, 'migration');
    }

    private function getNextBatchNumber(): int
    {
        $result = $this->connection->fetchOne(
            "SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}"
        );

        return ($result['max_batch'] ?? 0) + 1;
    }

    private function getLastBatchNumber(): ?int
    {
        $result = $this->connection->fetchOne(
            "SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}"
        );

        $maxBatch = $result['max_batch'] ?? null;
        return $maxBatch !== null ? (int) $maxBatch : null;
    }

    private function loadMigration(string $migrationName): Migration
    {
        $filePath = $this->migrationsPath . '/' . $migrationName . '.php';

        if (!file_exists($filePath)) {
            throw new RuntimeException("Migration file not found: {$filePath}");
        }

        require_once $filePath;

        $className = $migrationName;

        if (!class_exists($className)) {
            throw new RuntimeException("Migration class not found: {$className}");
        }

        $migration = new $className($this->connection);

        if (!$migration instanceof Migration) {
            throw new RuntimeException(
                "Migration class must extend Framework\\Database\\Migration: {$className}"
            );
        }

        return $migration;
    }

    private function recordMigration(string $migrationName, int $batch): void
    {
        $this->connection->execute(
            "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)",
            [$migrationName, $batch]
        );
    }

    private function removeMigrationRecord(string $migrationName): void
    {
        $this->connection->execute(
            "DELETE FROM {$this->migrationsTable} WHERE migration = ?",
            [$migrationName]
        );
    }
}
