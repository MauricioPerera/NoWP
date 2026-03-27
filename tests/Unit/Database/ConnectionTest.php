<?php

/**
 * Unit tests for Database Connection class
 */

declare(strict_types=1);

use ChimeraNoWP\Database\Connection;

beforeEach(function () {
    // Use SQLite in-memory database for unit tests
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
            'delay' => 10, // Use shorter delay for tests
        ],
    ];
    
    // MySQL config for testing MySQL-specific features
    $this->mysqlConfig = [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => (int) env('DB_PORT', 3306),
                'database' => env('DB_DATABASE', 'framework_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
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

it('creates a PDO connection successfully', function () {
    $connection = new Connection($this->config);
    $pdo = $connection->getPdo();
    
    expect($pdo)->toBeInstanceOf(PDO::class);
});

it('reuses the same PDO instance on multiple calls', function () {
    $connection = new Connection($this->config);
    $pdo1 = $connection->getPdo();
    $pdo2 = $connection->getPdo();
    
    expect($pdo1)->toBe($pdo2);
});

it('executes queries with prepared statements', function () {
    $connection = new Connection($this->config);
    
    // Create a test table (SQLite syntax)
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    
    // Insert data using prepared statement
    $affected = $connection->execute(
        'INSERT INTO test_users (name) VALUES (?)',
        ['John Doe']
    );
    
    expect($affected)->toBe(1);
});

it('fetches all results from a query', function () {
    $connection = new Connection($this->config);
    
    // Create and populate test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    $connection->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
    $connection->execute('INSERT INTO test_users (name) VALUES (?)', ['Bob']);
    
    $results = $connection->fetchAll('SELECT * FROM test_users ORDER BY id');
    
    expect($results)->toHaveCount(2)
        ->and($results[0]['name'])->toBe('Alice')
        ->and($results[1]['name'])->toBe('Bob');
});

it('fetches a single row from a query', function () {
    $connection = new Connection($this->config);
    
    // Create and populate test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    $connection->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
    
    $result = $connection->fetchOne('SELECT * FROM test_users WHERE name = ?', ['Alice']);
    
    expect($result)->toBeArray()
        ->and($result['name'])->toBe('Alice');
});

it('returns false when fetching non-existent row', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    
    $result = $connection->fetchOne('SELECT * FROM test_users WHERE name = ?', ['NonExistent']);
    
    expect($result)->toBeFalse();
});

it('returns last inserted ID', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    $connection->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
    
    $lastId = $connection->lastInsertId();
    
    expect($lastId)->toBe('1');
});

it('prevents SQL injection with prepared statements', function () {
    $connection = new Connection($this->config);
    
    // Create and populate test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    $connection->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
    
    // Attempt SQL injection
    $maliciousInput = "' OR '1'='1";
    $results = $connection->fetchAll('SELECT * FROM test_users WHERE name = ?', [$maliciousInput]);
    
    // Should return empty array, not all rows
    expect($results)->toBeArray()->toBeEmpty();
});

it('handles transaction commit', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    
    $connection->beginTransaction();
    $connection->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
    $connection->commit();
    
    $results = $connection->fetchAll('SELECT * FROM test_users');
    
    expect($results)->toHaveCount(1);
});

it('handles transaction rollback', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    
    $connection->beginTransaction();
    $connection->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
    $connection->rollback();
    
    $results = $connection->fetchAll('SELECT * FROM test_users');
    
    expect($results)->toBeEmpty();
});

it('detects when in transaction', function () {
    $connection = new Connection($this->config);
    
    expect($connection->inTransaction())->toBeFalse();
    
    $connection->beginTransaction();
    expect($connection->inTransaction())->toBeTrue();
    
    $connection->commit();
    expect($connection->inTransaction())->toBeFalse();
});

it('can disconnect and reconnect', function () {
    $connection = new Connection($this->config);
    
    $pdo1 = $connection->getPdo();
    expect($pdo1)->toBeInstanceOf(PDO::class);
    
    $connection->disconnect();
    
    $pdo2 = $connection->getPdo();
    expect($pdo2)->toBeInstanceOf(PDO::class)
        ->and($pdo2)->not->toBe($pdo1); // Should be a new instance
});

it('throws exception for invalid credentials', function () {
    $invalidConfig = $this->mysqlConfig;
    $invalidConfig['connections']['mysql']['password'] = 'invalid_password_12345';
    
    $connection = new Connection($invalidConfig);
    
    expect(fn() => $connection->getPdo())
        ->toThrow(PDOException::class);
})->skip('Requires MySQL server running');

it('throws exception for non-existent database', function () {
    $invalidConfig = $this->mysqlConfig;
    $invalidConfig['connections']['mysql']['database'] = 'non_existent_database_xyz';
    
    $connection = new Connection($invalidConfig);
    
    expect(fn() => $connection->getPdo())
        ->toThrow(PDOException::class);
})->skip('Requires MySQL server running');

it('uses configured charset and collation', function () {
    $connection = new Connection($this->mysqlConfig);
    $pdo = $connection->getPdo();
    
    $result = $pdo->query("SHOW VARIABLES LIKE 'character_set_connection'")->fetch();
    
    expect($result['Value'])->toBe('utf8mb4');
})->skip('Requires MySQL server running');

it('retries connection on failure', function () {
    // Create a config with an invalid SQLite database path that will fail
    $invalidConfig = $this->config;
    $invalidConfig['connections']['sqlite']['database'] = '/invalid/path/database.db';
    
    $connection = new Connection($invalidConfig);
    
    // Should throw exception after 3 retries
    expect(fn() => $connection->getPdo())
        ->toThrow(PDOException::class, 'Failed to connect to database after 3 attempts');
});

it('supports SQLite in-memory databases', function () {
    $connection = new Connection($this->config);
    $pdo = $connection->getPdo();
    
    expect($pdo)->toBeInstanceOf(PDO::class);
    
    // Verify it's SQLite
    $result = $pdo->query("SELECT sqlite_version()")->fetch();
    expect($result)->toHaveKey('sqlite_version()');
});

it('executes callback within transaction and commits on success', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    
    $result = $connection->transaction(function ($conn) {
        $conn->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
        $conn->execute('INSERT INTO test_users (name) VALUES (?)', ['Bob']);
        return 'success';
    });
    
    expect($result)->toBe('success');
    
    $users = $connection->fetchAll('SELECT * FROM test_users');
    expect($users)->toHaveCount(2);
});

it('automatically rolls back transaction on exception', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    
    try {
        $connection->transaction(function ($conn) {
            $conn->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
            throw new \Exception('Something went wrong');
        });
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Something went wrong');
    }
    
    // Verify rollback occurred - no data should be inserted
    $users = $connection->fetchAll('SELECT * FROM test_users');
    expect($users)->toBeEmpty();
});

it('automatically rolls back transaction on PDO exception', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    
    try {
        $connection->transaction(function ($conn) {
            $conn->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
            // This will fail due to NOT NULL constraint
            $conn->execute('INSERT INTO test_users (name) VALUES (NULL)');
        });
    } catch (PDOException $e) {
        expect($e)->toBeInstanceOf(PDOException::class);
    }
    
    // Verify rollback occurred - no data should be inserted
    $users = $connection->fetchAll('SELECT * FROM test_users');
    expect($users)->toBeEmpty();
});

it('returns callback result from transaction', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    
    $lastId = $connection->transaction(function ($conn) {
        $conn->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
        return $conn->lastInsertId();
    });
    
    expect($lastId)->toBe('1');
});

it('handles nested transaction attempts gracefully', function () {
    $connection = new Connection($this->config);
    
    // Create test table
    $connection->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    
    $connection->transaction(function ($conn) {
        $conn->execute('INSERT INTO test_users (name) VALUES (?)', ['Alice']);
        
        // Nested transaction - PDO doesn't support true nested transactions
        // This should still work but won't create a new transaction level
        $conn->transaction(function ($c) {
            $c->execute('INSERT INTO test_users (name) VALUES (?)', ['Bob']);
        });
    });
    
    $users = $connection->fetchAll('SELECT * FROM test_users');
    expect($users)->toHaveCount(2);
});
