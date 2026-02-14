<?php

/**
 * Unit tests for QueryBuilder class
 */

declare(strict_types=1);

use Framework\Database\Connection;
use Framework\Database\QueryBuilder;
use PDO;

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
            'delay' => 10,
        ],
    ];
    
    $this->connection = new Connection($this->config);
    $this->builder = new QueryBuilder($this->connection);
    
    // Create test table
    $this->connection->execute('
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            age INTEGER,
            status TEXT DEFAULT "active"
        )
    ');
    
    // Create posts table for join tests
    $this->connection->execute('
        CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            content TEXT
        )
    ');
});

describe('Basic SELECT queries', function () {
    it('selects all columns from a table', function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 30]);
        
        $results = $this->builder->table('users')->get();
        
        expect($results)->toHaveCount(2)
            ->and($results[0]['name'])->toBe('Alice')
            ->and($results[1]['name'])->toBe('Bob');
    });
    
    it('selects specific columns', function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        
        $results = $this->builder->table('users')->select(['name', 'email'])->get();
        
        expect($results[0])->toHaveKeys(['name', 'email'])
            ->and($results[0])->not->toHaveKey('age');
    });
    
    it('gets first result', function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 30]);
        
        $result = $this->builder->table('users')->first();
        
        expect($result)->toBeArray()
            ->and($result['name'])->toBe('Alice');
    });
    
    it('returns null when no results found', function () {
        $result = $this->builder->table('users')->where('name', 'NonExistent')->first();
        
        expect($result)->toBeNull();
    });
    
    it('counts rows', function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 30]);
        
        $count = $this->builder->table('users')->count();
        
        expect($count)->toBe(2);
    });
});

describe('WHERE clauses', function () {
    beforeEach(function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 30]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Charlie', 'charlie@example.com', 35]);
    });
    
    it('filters with WHERE clause', function () {
        $results = $this->builder->table('users')->where('name', 'Alice')->get();
        
        expect($results)->toHaveCount(1)
            ->and($results[0]['name'])->toBe('Alice');
    });
    
    it('supports different operators', function () {
        $results = $this->builder->table('users')->where('age', 30, '>')->get();
        
        expect($results)->toHaveCount(1)
            ->and($results[0]['name'])->toBe('Charlie');
    });
    
    it('chains multiple WHERE clauses', function () {
        $results = $this->builder->table('users')
            ->where('age', 25, '>')
            ->where('age', 35, '<')
            ->get();
        
        expect($results)->toHaveCount(1)
            ->and($results[0]['name'])->toBe('Bob');
    });
    
    it('supports OR WHERE clauses', function () {
        $results = $this->builder->table('users')
            ->where('name', 'Alice')
            ->orWhere('name', 'Charlie')
            ->get();
        
        expect($results)->toHaveCount(2);
    });
    
    it('supports WHERE IN clause', function () {
        $results = $this->builder->table('users')
            ->whereIn('name', ['Alice', 'Bob'])
            ->get();
        
        expect($results)->toHaveCount(2);
    });
    
    it('supports WHERE NULL clause', function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Dave', 'dave@example.com', null]);
        
        $results = $this->builder->table('users')->whereNull('age')->get();
        
        expect($results)->toHaveCount(1)
            ->and($results[0]['name'])->toBe('Dave');
    });
    
    it('supports WHERE NOT NULL clause', function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Dave', 'dave@example.com', null]);
        
        $results = $this->builder->table('users')->whereNotNull('age')->get();
        
        expect($results)->toHaveCount(3);
    });
});

describe('JOIN clauses', function () {
    beforeEach(function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 30]);
        
        $this->connection->execute('INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)', [1, 'First Post', 'Content 1']);
        $this->connection->execute('INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)', [1, 'Second Post', 'Content 2']);
        $this->connection->execute('INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)', [2, 'Bob Post', 'Content 3']);
    });
    
    it('performs INNER JOIN', function () {
        $results = $this->builder->table('users')
            ->select(['users.name', 'posts.title'])
            ->join('posts', 'users.id', 'posts.user_id')
            ->get();
        
        expect($results)->toHaveCount(3);
    });
    
    it('performs LEFT JOIN', function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Charlie', 'charlie@example.com', 35]);
        
        $results = $this->builder->table('users')
            ->select(['users.name', 'posts.title'])
            ->leftJoin('posts', 'users.id', 'posts.user_id')
            ->get();
        
        expect($results)->toHaveCount(4); // 3 posts + 1 user without posts
    });
    
    it('combines JOIN with WHERE', function () {
        $results = $this->builder->table('users')
            ->select(['users.name', 'posts.title'])
            ->join('posts', 'users.id', 'posts.user_id')
            ->where('users.name', 'Alice')
            ->get();
        
        expect($results)->toHaveCount(2);
    });
});

describe('ORDER BY and LIMIT', function () {
    beforeEach(function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Charlie', 'charlie@example.com', 35]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 30]);
    });
    
    it('orders results ascending', function () {
        $results = $this->builder->table('users')->orderBy('name', 'asc')->get();
        
        expect($results[0]['name'])->toBe('Alice')
            ->and($results[1]['name'])->toBe('Bob')
            ->and($results[2]['name'])->toBe('Charlie');
    });
    
    it('orders results descending', function () {
        $results = $this->builder->table('users')->orderBy('age', 'desc')->get();
        
        expect($results[0]['age'])->toBe(35)
            ->and($results[1]['age'])->toBe(30)
            ->and($results[2]['age'])->toBe(25);
    });
    
    it('limits results', function () {
        $results = $this->builder->table('users')->limit(2)->get();
        
        expect($results)->toHaveCount(2);
    });
    
    it('supports offset', function () {
        $results = $this->builder->table('users')
            ->orderBy('name', 'asc')
            ->limit(2)
            ->offset(1)
            ->get();
        
        expect($results)->toHaveCount(2)
            ->and($results[0]['name'])->toBe('Bob');
    });
    
    it('orders by multiple columns', function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice2@example.com', 30]);
        
        $results = $this->builder->table('users')
            ->orderBy('name', 'asc')
            ->orderBy('age', 'desc')
            ->get();
        
        expect($results[0]['name'])->toBe('Alice')
            ->and($results[0]['age'])->toBe(30);
    });
});

describe('INSERT operations', function () {
    it('inserts a new record', function () {
        $id = $this->builder->table('users')->insert([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'age' => 25
        ]);
        
        expect($id)->toBeInt()->toBeGreaterThan(0);
        
        $result = $this->builder->table('users')->where('id', $id)->first();
        expect($result['name'])->toBe('Alice');
    });
    
    it('returns last inserted ID', function () {
        $id1 = $this->builder->table('users')->insert([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'age' => 25
        ]);
        
        $id2 = $this->builder->table('users')->insert([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'age' => 30
        ]);
        
        expect($id2)->toBeGreaterThan($id1);
    });
    
    it('throws exception for empty insert data', function () {
        expect(fn() => $this->builder->table('users')->insert([]))
            ->toThrow(\InvalidArgumentException::class, 'Insert data cannot be empty');
    });
});

describe('UPDATE operations', function () {
    beforeEach(function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 30]);
    });
    
    it('updates records', function () {
        $affected = $this->builder->table('users')
            ->where('name', 'Alice')
            ->update(['age' => 26]);
        
        expect($affected)->toBe(1);
        
        $result = $this->builder->table('users')->where('name', 'Alice')->first();
        expect($result['age'])->toBe(26);
    });
    
    it('updates multiple records', function () {
        $affected = $this->builder->table('users')
            ->where('age', 20, '>')
            ->update(['status' => 'verified']);
        
        expect($affected)->toBe(2);
    });
    
    it('updates multiple columns', function () {
        $this->builder->table('users')
            ->where('name', 'Alice')
            ->update([
                'age' => 26,
                'email' => 'alice.new@example.com'
            ]);
        
        $result = $this->builder->table('users')->where('name', 'Alice')->first();
        expect($result['age'])->toBe(26)
            ->and($result['email'])->toBe('alice.new@example.com');
    });
    
    it('throws exception for empty update data', function () {
        expect(fn() => $this->builder->table('users')->update([]))
            ->toThrow(\InvalidArgumentException::class, 'Update data cannot be empty');
    });
});

describe('DELETE operations', function () {
    beforeEach(function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 30]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Charlie', 'charlie@example.com', 35]);
    });
    
    it('deletes records', function () {
        $affected = $this->builder->table('users')
            ->where('name', 'Alice')
            ->delete();
        
        expect($affected)->toBe(1);
        
        // Create new builder instance for count
        $count = (new QueryBuilder($this->connection))->table('users')->count();
        expect($count)->toBe(2);
    });
    
    it('deletes multiple records', function () {
        $affected = $this->builder->table('users')
            ->where('age', 30, '>=')
            ->delete();
        
        expect($affected)->toBe(2);
        
        // Create new builder instance for count
        $count = (new QueryBuilder($this->connection))->table('users')->count();
        expect($count)->toBe(1);
    });
});

describe('SQL injection prevention', function () {
    beforeEach(function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 30]);
    });
    
    it('prevents SQL injection in WHERE clause', function () {
        $maliciousInput = "' OR '1'='1";
        $results = $this->builder->table('users')->where('name', $maliciousInput)->get();
        
        expect($results)->toBeEmpty();
    });
    
    it('prevents SQL injection in INSERT', function () {
        $maliciousInput = "'; DROP TABLE users; --";
        
        $this->builder->table('users')->insert([
            'name' => $maliciousInput,
            'email' => 'test@example.com',
            'age' => 25
        ]);
        
        // Table should still exist
        $count = $this->builder->table('users')->count();
        expect($count)->toBe(3);
        
        // Malicious input should be stored as literal string
        $result = $this->builder->table('users')->where('email', 'test@example.com')->first();
        expect($result['name'])->toBe($maliciousInput);
    });
    
    it('prevents SQL injection in UPDATE', function () {
        $maliciousInput = "'; DROP TABLE users; --";
        
        $this->builder->table('users')
            ->where('name', 'Alice')
            ->update(['email' => $maliciousInput]);
        
        // Table should still exist - create new builder instance
        $count = (new QueryBuilder($this->connection))->table('users')->count();
        expect($count)->toBe(2);
        
        // Malicious input should be stored as literal string
        $result = (new QueryBuilder($this->connection))->table('users')->where('name', 'Alice')->first();
        expect($result['email'])->toBe($maliciousInput);
    });
    
    it('prevents SQL injection in WHERE IN', function () {
        $maliciousInput = ["Alice", "' OR '1'='1"];
        $results = $this->builder->table('users')->whereIn('name', $maliciousInput)->get();
        
        expect($results)->toHaveCount(1)
            ->and($results[0]['name'])->toBe('Alice');
    });
});

describe('Fluent interface', function () {
    it('allows method chaining', function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 30]);
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Charlie', 'charlie@example.com', 35]);
        
        $results = $this->builder->table('users')
            ->select(['name', 'age'])
            ->where('age', 25, '>')
            ->orderBy('age', 'desc')
            ->limit(2)
            ->get();
        
        expect($results)->toHaveCount(2)
            ->and($results[0]['name'])->toBe('Charlie')
            ->and($results[1]['name'])->toBe('Bob');
    });
    
    it('can be reused for multiple queries', function () {
        $this->connection->execute('INSERT INTO users (name, email, age) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 25]);
        
        $builder = $this->builder->table('users');
        
        $count = $builder->count();
        expect($count)->toBe(1);
        
        $results = $builder->get();
        expect($results)->toHaveCount(1);
    });
});

describe('Debugging helpers', function () {
    it('generates SQL query string', function () {
        $sql = $this->builder->table('users')
            ->select(['name', 'email'])
            ->where('age', 25, '>')
            ->orderBy('name', 'asc')
            ->limit(10)
            ->toSql();
        
        expect($sql)->toContain('SELECT name, email')
            ->and($sql)->toContain('FROM users')
            ->and($sql)->toContain('WHERE')
            ->and($sql)->toContain('ORDER BY')
            ->and($sql)->toContain('LIMIT');
    });
    
    it('exposes bindings', function () {
        $this->builder->table('users')
            ->where('age', 25)
            ->where('name', 'Alice')
            ->toSql();
        
        $bindings = $this->builder->getBindings();
        
        expect($bindings)->toBe([25, 'Alice']);
    });
});
