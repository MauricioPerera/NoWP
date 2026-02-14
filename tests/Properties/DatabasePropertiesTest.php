<?php

/**
 * Property-Based Tests for Database Layer
 * 
 * These tests verify universal properties that should hold true
 * across all valid inputs and scenarios.
 */

declare(strict_types=1);

use Framework\Database\Connection;
use PDO;

beforeEach(function () {
    // Use SQLite in-memory database for property tests
    $this->config = [
        'default' => 'sqlite',
        'connections' => [
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'charset' => 'utf8',
                'collation' => '',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
        ],
        'retry' => [
            'attempts' => 3,
            'delay' => 10,
        ],
    ];
});

/**
 * Property 14: Rollback automático en transacciones
 * 
 * Para cualquier transacción de base de datos que encuentra un error durante su ejecución,
 * el sistema debe hacer rollback automático de todos los cambios realizados en esa transacción.
 * 
 * Feature: wordpress-alternative-framework, Property 14: Rollback automático en transacciones
 * Validates: Requirements 5.6
 */
it('automatically rolls back all changes when transaction encounters any error', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_data (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL)');
    
    // Generate random test data
    $testValues = [];
    for ($i = 0; $i < rand(5, 15); $i++) {
        $testValues[] = 'value_' . bin2hex(random_bytes(8));
    }
    
    // Insert some initial data
    $initialCount = rand(0, 5);
    for ($i = 0; $i < $initialCount; $i++) {
        $connection->execute('INSERT INTO test_data (value) VALUES (?)', ['initial_' . $i]);
    }
    
    // Verify initial state
    $beforeCount = $connection->fetchOne('SELECT COUNT(*) as count FROM test_data')['count'];
    expect($beforeCount)->toBe($initialCount);
    
    // Attempt transaction that will fail at random point
    $failurePoint = rand(1, count($testValues) - 1);
    
    try {
        $connection->transaction(function ($conn) use ($testValues, $failurePoint) {
            foreach ($testValues as $index => $value) {
                if ($index === $failurePoint) {
                    // Trigger an error by violating NOT NULL constraint
                    $conn->execute('INSERT INTO test_data (value) VALUES (NULL)');
                }
                $conn->execute('INSERT INTO test_data (value) VALUES (?)', [$value]);
            }
        });
    } catch (\Throwable $e) {
        // Expected to fail
    }
    
    // Verify rollback occurred - count should be unchanged
    $afterCount = $connection->fetchOne('SELECT COUNT(*) as count FROM test_data')['count'];
    expect($afterCount)->toBe($beforeCount)
        ->and($afterCount)->toBe($initialCount);
})->repeat(100);

/**
 * Property 14 (variant): Rollback on exception types
 * 
 * Tests that rollback occurs for different types of exceptions
 */
it('rolls back transaction on any throwable type', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_data (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT)');
    
    // Test different exception types
    $exceptionTypes = [
        new \Exception('Generic exception'),
        new \RuntimeException('Runtime exception'),
        new \LogicException('Logic exception'),
        new \PDOException('PDO exception'),
    ];
    
    $exceptionType = $exceptionTypes[array_rand($exceptionTypes)];
    
    try {
        $connection->transaction(function ($conn) use ($exceptionType) {
            // Insert some data
            $conn->execute('INSERT INTO test_data (value) VALUES (?)', ['test_value']);
            
            // Throw the exception
            throw $exceptionType;
        });
    } catch (\Throwable $e) {
        // Expected
    }
    
    // Verify rollback occurred
    $count = $connection->fetchOne('SELECT COUNT(*) as count FROM test_data')['count'];
    expect($count)->toBe(0);
})->repeat(50);

/**
 * Property 14 (variant): Successful transactions commit all changes
 * 
 * Verifies that when no error occurs, all changes are committed
 */
it('commits all changes when transaction completes successfully', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_data (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT)');
    
    // Generate random number of inserts
    $insertCount = rand(10, 50);
    
    $connection->transaction(function ($conn) use ($insertCount) {
        for ($i = 0; $i < $insertCount; $i++) {
            $conn->execute('INSERT INTO test_data (value) VALUES (?)', ['value_' . $i]);
        }
    });
    
    // Verify all changes were committed
    $count = $connection->fetchOne('SELECT COUNT(*) as count FROM test_data')['count'];
    expect($count)->toBe($insertCount);
})->repeat(100);

/**
 * Property 14 (variant): Nested transaction rollback
 * 
 * Verifies that when a nested transaction fails, the entire transaction is rolled back
 */
it('rolls back entire transaction when nested operation fails', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_data (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL)');
    
    $outerInserts = rand(3, 8);
    $innerInserts = rand(3, 8);
    
    try {
        $connection->transaction(function ($conn) use ($outerInserts, $innerInserts) {
            // Insert some data in outer transaction
            for ($i = 0; $i < $outerInserts; $i++) {
                $conn->execute('INSERT INTO test_data (value) VALUES (?)', ['outer_' . $i]);
            }
            
            // Nested transaction that will fail
            $conn->transaction(function ($c) use ($innerInserts) {
                for ($i = 0; $i < $innerInserts; $i++) {
                    $c->execute('INSERT INTO test_data (value) VALUES (?)', ['inner_' . $i]);
                }
                
                // Cause failure
                throw new \Exception('Nested failure');
            });
        });
    } catch (\Throwable $e) {
        // Expected
    }
    
    // Verify complete rollback - no data should exist
    $count = $connection->fetchOne('SELECT COUNT(*) as count FROM test_data')['count'];
    expect($count)->toBe(0);
})->repeat(50);
