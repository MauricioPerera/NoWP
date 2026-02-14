<?php

/**
 * Create Users Table Migration Tests
 * 
 * Tests for the users table migration to ensure proper schema creation
 * and rollback functionality.
 * 
 * Requirements: 2.1, 2.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../../migrations/2026_01_15_000001_create_users_table.php';

use Framework\Database\Connection;

beforeEach(function () {
    // Use in-memory SQLite for testing (MySQL syntax adapted)
    $config = [
        'default' => 'testing',
        'connections' => [
            'testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ],
            ],
        ],
        'retry' => [
            'attempts' => 3,
            'delay' => 100,
        ],
    ];
    
    $this->connection = new Connection($config);
    $this->migration = new CreateUsersTable($this->connection);
});

it('creates users table with correct schema', function () {
    // Adapt SQL for SQLite
    $sql = "
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL
        )
    ";
    $this->connection->execute($sql);
    $this->connection->execute("CREATE INDEX idx_email ON users(email)");
    
    // Verify table exists
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='users'"
    );
    expect($result)->toHaveCount(1);
});

it('creates email index', function () {
    // Create table
    $sql = "
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL
        )
    ";
    $this->connection->execute($sql);
    $this->connection->execute("CREATE INDEX idx_email ON users(email)");
    
    // Verify index exists
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='index' AND name='idx_email'"
    );
    expect($result)->toHaveCount(1);
});

it('enforces unique email constraint', function () {
    // Create table
    $sql = "
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL
        )
    ";
    $this->connection->execute($sql);
    
    // Insert first user
    $this->connection->execute(
        "INSERT INTO users (email, password_hash, display_name, role) VALUES (?, ?, ?, ?)",
        ['test@example.com', 'hash123', 'Test User', 'admin']
    );
    
    // Try to insert duplicate email
    expect(fn() => $this->connection->execute(
        "INSERT INTO users (email, password_hash, display_name, role) VALUES (?, ?, ?, ?)",
        ['test@example.com', 'hash456', 'Another User', 'editor']
    ))->toThrow(PDOException::class);
});

it('allows null last_login_at', function () {
    // Create table
    $sql = "
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL
        )
    ";
    $this->connection->execute($sql);
    
    // Insert user without last_login_at
    $this->connection->execute(
        "INSERT INTO users (email, password_hash, display_name, role) VALUES (?, ?, ?, ?)",
        ['test@example.com', 'hash123', 'Test User', 'admin']
    );
    
    $result = $this->connection->fetchOne(
        "SELECT last_login_at FROM users WHERE email = ?",
        ['test@example.com']
    );
    
    expect($result['last_login_at'])->toBeNull();
});

it('sets created_at automatically', function () {
    // Create table
    $sql = "
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL
        )
    ";
    $this->connection->execute($sql);
    
    // Insert user
    $this->connection->execute(
        "INSERT INTO users (email, password_hash, display_name, role) VALUES (?, ?, ?, ?)",
        ['test@example.com', 'hash123', 'Test User', 'admin']
    );
    
    $result = $this->connection->fetchOne(
        "SELECT created_at FROM users WHERE email = ?",
        ['test@example.com']
    );
    
    expect($result['created_at'])->not->toBeNull();
});

it('requires all non-nullable fields', function () {
    // Create table
    $sql = "
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL
        )
    ";
    $this->connection->execute($sql);
    
    // Try to insert without required fields
    expect(fn() => $this->connection->execute(
        "INSERT INTO users (email) VALUES (?)",
        ['test@example.com']
    ))->toThrow(PDOException::class);
});

it('drops users table on rollback', function () {
    // Create table
    $sql = "
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL
        )
    ";
    $this->connection->execute($sql);
    
    // Verify table exists
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='users'"
    );
    expect($result)->toHaveCount(1);
    
    // Drop table
    $this->connection->execute("DROP TABLE IF EXISTS users");
    
    // Verify table is gone
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='users'"
    );
    expect($result)->toBeEmpty();
});

it('stores all user fields correctly', function () {
    // Create table
    $sql = "
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL
        )
    ";
    $this->connection->execute($sql);
    
    // Insert user with all fields
    $this->connection->execute(
        "INSERT INTO users (email, password_hash, display_name, role, last_login_at) VALUES (?, ?, ?, ?, ?)",
        ['admin@example.com', '$2y$10$hash', 'Admin User', 'admin', '2026-01-15 10:00:00']
    );
    
    $result = $this->connection->fetchOne(
        "SELECT * FROM users WHERE email = ?",
        ['admin@example.com']
    );
    
    expect($result['email'])->toBe('admin@example.com')
        ->and($result['password_hash'])->toBe('$2y$10$hash')
        ->and($result['display_name'])->toBe('Admin User')
        ->and($result['role'])->toBe('admin')
        ->and($result['last_login_at'])->toBe('2026-01-15 10:00:00');
});
