<?php

/**
 * UserRepository Unit Tests
 *
 * Tests for UserRepository database operations using SQLite in-memory.
 */

declare(strict_types=1);

use ChimeraNoWP\Auth\UserRepository;
use ChimeraNoWP\Auth\User;
use ChimeraNoWP\Auth\UserRole;
use ChimeraNoWP\Auth\PasswordHasher;
use ChimeraNoWP\Database\Connection;
use PDO;

beforeEach(function () {
    $this->connection = new Connection([
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
    ]);

    $this->connection->getPdo()->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            last_login_at TEXT
        )
    ");

    $this->hasher = new PasswordHasher();
    $this->repository = new UserRepository($this->connection);
});

// ---------------------------------------------------------------------------
// create
// ---------------------------------------------------------------------------

describe('create', function () {
    it('creates a user and returns User instance', function () {
        $user = $this->repository->create([
            'email' => 'alice@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Alice',
            'role' => UserRole::EDITOR->value,
        ]);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->id)->toBeGreaterThan(0)
            ->and($user->email)->toBe('alice@example.com')
            ->and($user->displayName)->toBe('Alice')
            ->and($user->role)->toBe(UserRole::EDITOR);
    });

    it('defaults role to subscriber when not provided', function () {
        $user = $this->repository->create([
            'email' => 'bob@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Bob',
        ]);

        expect($user->role)->toBe(UserRole::SUBSCRIBER);
    });
});

// ---------------------------------------------------------------------------
// find
// ---------------------------------------------------------------------------

describe('find', function () {
    it('returns user by ID', function () {
        $created = $this->repository->create([
            'email' => 'alice@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Alice',
            'role' => UserRole::ADMIN->value,
        ]);

        $found = $this->repository->find($created->id);

        expect($found)->toBeInstanceOf(User::class)
            ->and($found->id)->toBe($created->id)
            ->and($found->email)->toBe('alice@example.com')
            ->and($found->displayName)->toBe('Alice')
            ->and($found->role)->toBe(UserRole::ADMIN);
    });

    it('returns null for non-existent ID', function () {
        $found = $this->repository->find(999);

        expect($found)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// findByEmail
// ---------------------------------------------------------------------------

describe('findByEmail', function () {
    it('returns user by email address', function () {
        $this->repository->create([
            'email' => 'alice@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Alice',
        ]);

        $found = $this->repository->findByEmail('alice@example.com');

        expect($found)->toBeInstanceOf(User::class)
            ->and($found->email)->toBe('alice@example.com');
    });

    it('returns null for non-existent email', function () {
        $found = $this->repository->findByEmail('nobody@example.com');

        expect($found)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// findAll
// ---------------------------------------------------------------------------

describe('findAll', function () {
    beforeEach(function () {
        $this->repository->create([
            'email' => 'admin@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Admin User',
            'role' => UserRole::ADMIN->value,
        ]);
        $this->repository->create([
            'email' => 'editor@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Editor User',
            'role' => UserRole::EDITOR->value,
        ]);
        $this->repository->create([
            'email' => 'author@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Author User',
            'role' => UserRole::AUTHOR->value,
        ]);
    });

    it('returns all users when no filters', function () {
        $users = $this->repository->findAll();

        expect($users)->toHaveCount(3)
            ->and($users[0])->toBeInstanceOf(User::class);
    });

    it('filters by role', function () {
        $admins = $this->repository->findAll(['role' => 'admin']);

        expect($admins)->toHaveCount(1)
            ->and($admins[0]->role)->toBe(UserRole::ADMIN);
    });

    it('searches by email or display_name', function () {
        $results = $this->repository->findAll(['search' => 'editor']);

        expect($results)->toHaveCount(1)
            ->and($results[0]->email)->toBe('editor@example.com');
    });

    it('searches display_name partial match', function () {
        $results = $this->repository->findAll(['search' => 'Author']);

        expect($results)->toHaveCount(1)
            ->and($results[0]->displayName)->toBe('Author User');
    });

    it('paginates with limit and offset', function () {
        $page1 = $this->repository->findAll(['limit' => 2, 'offset' => 0]);
        $page2 = $this->repository->findAll(['limit' => 2, 'offset' => 2]);

        expect($page1)->toHaveCount(2)
            ->and($page2)->toHaveCount(1);
    });

    it('orders results by specified column', function () {
        $users = $this->repository->findAll([
            'order_by' => 'email',
            'order_direction' => 'asc',
        ]);

        expect($users[0]->email)->toBe('admin@example.com')
            ->and($users[1]->email)->toBe('author@example.com')
            ->and($users[2]->email)->toBe('editor@example.com');
    });
});

// ---------------------------------------------------------------------------
// update
// ---------------------------------------------------------------------------

describe('update', function () {
    it('updates user email', function () {
        $user = $this->repository->create([
            'email' => 'old@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Test User',
        ]);

        $updated = $this->repository->update($user->id, [
            'email' => 'new@example.com',
        ]);

        expect($updated->email)->toBe('new@example.com');
    });

    it('updates user display_name', function () {
        $user = $this->repository->create([
            'email' => 'test@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Old Name',
        ]);

        $updated = $this->repository->update($user->id, [
            'display_name' => 'New Name',
        ]);

        expect($updated->displayName)->toBe('New Name');
    });

    it('updates user role', function () {
        $user = $this->repository->create([
            'email' => 'test@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Test User',
            'role' => UserRole::SUBSCRIBER->value,
        ]);

        $updated = $this->repository->update($user->id, [
            'role' => UserRole::EDITOR->value,
        ]);

        expect($updated->role)->toBe(UserRole::EDITOR);
    });

    it('updates password hash', function () {
        $user = $this->repository->create([
            'email' => 'test@example.com',
            'password_hash' => $this->hasher->hash('oldpassword'),
            'display_name' => 'Test User',
        ]);

        $newHash = $this->hasher->hash('newpassword');
        $updated = $this->repository->update($user->id, [
            'password_hash' => $newHash,
        ]);

        expect($this->hasher->verify('newpassword', $updated->getPasswordHash()))->toBeTrue();
    });

    it('throws RuntimeException when updating non-existent user', function () {
        $this->repository->update(999, ['email' => 'fail@example.com']);
    })->throws(RuntimeException::class);
});

// ---------------------------------------------------------------------------
// delete
// ---------------------------------------------------------------------------

describe('delete', function () {
    it('deletes an existing user and returns true', function () {
        $user = $this->repository->create([
            'email' => 'delete-me@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Delete Me',
        ]);

        $result = $this->repository->delete($user->id);

        expect($result)->toBeTrue()
            ->and($this->repository->find($user->id))->toBeNull();
    });

    it('returns false when deleting non-existent user', function () {
        $result = $this->repository->delete(999);

        expect($result)->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// count
// ---------------------------------------------------------------------------

describe('count', function () {
    it('counts all users', function () {
        $this->repository->create([
            'email' => 'a@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'A',
            'role' => UserRole::ADMIN->value,
        ]);
        $this->repository->create([
            'email' => 'b@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'B',
            'role' => UserRole::EDITOR->value,
        ]);

        expect($this->repository->count())->toBe(2);
    });

    it('counts users filtered by role', function () {
        $this->repository->create([
            'email' => 'admin@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Admin',
            'role' => UserRole::ADMIN->value,
        ]);
        $this->repository->create([
            'email' => 'sub@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Sub',
            'role' => UserRole::SUBSCRIBER->value,
        ]);

        expect($this->repository->count(['role' => 'admin']))->toBe(1)
            ->and($this->repository->count(['role' => 'subscriber']))->toBe(1);
    });

    it('returns zero for empty table', function () {
        expect($this->repository->count())->toBe(0);
    });
});

// ---------------------------------------------------------------------------
// emailExists
// ---------------------------------------------------------------------------

describe('emailExists', function () {
    it('returns true when email exists', function () {
        $this->repository->create([
            'email' => 'exists@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Exists',
        ]);

        expect($this->repository->emailExists('exists@example.com'))->toBeTrue();
    });

    it('returns false when email does not exist', function () {
        expect($this->repository->emailExists('nope@example.com'))->toBeFalse();
    });

    it('excludes a specific user ID from the check', function () {
        $user = $this->repository->create([
            'email' => 'mine@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Mine',
        ]);

        // Same email, but excluded because it belongs to the same user
        expect($this->repository->emailExists('mine@example.com', $user->id))->toBeFalse();

        // Same email without exclusion
        expect($this->repository->emailExists('mine@example.com'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// updateLastLogin
// ---------------------------------------------------------------------------

describe('updateLastLogin', function () {
    it('updates the last_login_at timestamp', function () {
        $user = $this->repository->create([
            'email' => 'login@example.com',
            'password_hash' => $this->hasher->hash('password123'),
            'display_name' => 'Login User',
        ]);

        expect($user->lastLoginAt)->toBeNull();

        $this->repository->updateLastLogin($user->id);

        $refreshed = $this->repository->find($user->id);

        expect($refreshed->lastLoginAt)->not->toBeNull()
            ->and($refreshed->lastLoginAt)->toBeInstanceOf(DateTime::class);
    });
});
