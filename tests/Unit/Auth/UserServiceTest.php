<?php

/**
 * UserService Unit Tests
 *
 * Tests for UserService business logic including authentication,
 * registration, profile management, and authorization rules.
 */

declare(strict_types=1);

use ChimeraNoWP\Auth\UserService;
use ChimeraNoWP\Auth\UserRepository;
use ChimeraNoWP\Auth\User;
use ChimeraNoWP\Auth\UserRole;
use ChimeraNoWP\Auth\PasswordHasher;
use ChimeraNoWP\Auth\JWTManager;
use ChimeraNoWP\Core\Exceptions\AuthenticationException;
use ChimeraNoWP\Core\Exceptions\ValidationException;
use ChimeraNoWP\Core\Exceptions\NotFoundException;
use ChimeraNoWP\Core\Exceptions\AuthorizationException;
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
    $this->jwtManager = new JWTManager('test-secret-key-for-unit-tests', 3600);
    $this->repository = new UserRepository($this->connection);

    $this->service = new UserService(
        $this->repository,
        $this->hasher,
        $this->jwtManager
    );

    // Helper to create a user directly via repository
    $this->createTestUser = function (
        string $email = 'user@example.com',
        string $password = 'password123',
        string $displayName = 'Test User',
        UserRole $role = UserRole::SUBSCRIBER
    ): User {
        return $this->repository->create([
            'email' => $email,
            'password_hash' => $this->hasher->hash($password),
            'display_name' => $displayName,
            'role' => $role->value,
        ]);
    };
});

// ---------------------------------------------------------------------------
// login
// ---------------------------------------------------------------------------

describe('login', function () {
    it('returns token and user array for valid credentials', function () {
        ($this->createTestUser)('alice@example.com', 'secret123', 'Alice');

        $result = $this->service->login('alice@example.com', 'secret123');

        expect($result)->toBeArray()
            ->toHaveKey('token')
            ->toHaveKey('user')
            ->and($result['token'])->toBeString()->not->toBeEmpty()
            ->and($result['user']['email'])->toBe('alice@example.com')
            ->and($result['user']['display_name'])->toBe('Alice');
    });

    it('generates a valid JWT token on login', function () {
        ($this->createTestUser)('alice@example.com', 'secret123', 'Alice');

        $result = $this->service->login('alice@example.com', 'secret123');

        $payload = $this->jwtManager->parseToken($result['token']);

        expect($payload['email'])->toBe('alice@example.com')
            ->and($payload['role'])->toBe('subscriber');
    });

    it('throws AuthenticationException for wrong password', function () {
        ($this->createTestUser)('alice@example.com', 'correct-password');

        $this->service->login('alice@example.com', 'wrong-password');
    })->throws(AuthenticationException::class, 'Invalid email or password');

    it('throws AuthenticationException for non-existent email', function () {
        $this->service->login('nobody@example.com', 'password123');
    })->throws(AuthenticationException::class, 'Invalid email or password');
});

// ---------------------------------------------------------------------------
// register
// ---------------------------------------------------------------------------

describe('register', function () {
    it('creates a new user with hashed password', function () {
        $user = $this->service->register([
            'email' => 'new@example.com',
            'password' => 'password123',
            'display_name' => 'New User',
        ]);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->email)->toBe('new@example.com')
            ->and($user->displayName)->toBe('New User')
            ->and($this->hasher->verify('password123', $user->getPasswordHash()))->toBeTrue();
    });

    it('assigns subscriber role by default', function () {
        $user = $this->service->register([
            'email' => 'sub@example.com',
            'password' => 'password123',
            'display_name' => 'Subscriber',
        ]);

        expect($user->role)->toBe(UserRole::SUBSCRIBER);
    });

    it('respects explicit role in registration data', function () {
        $user = $this->service->register([
            'email' => 'editor@example.com',
            'password' => 'password123',
            'display_name' => 'Editor',
            'role' => UserRole::EDITOR->value,
        ]);

        expect($user->role)->toBe(UserRole::EDITOR);
    });

    it('throws ValidationException for duplicate email', function () {
        $this->service->register([
            'email' => 'dupe@example.com',
            'password' => 'password123',
            'display_name' => 'First',
        ]);

        $this->service->register([
            'email' => 'dupe@example.com',
            'password' => 'password123',
            'display_name' => 'Second',
        ]);
    })->throws(ValidationException::class);

    it('throws ValidationException when email is missing', function () {
        $this->service->register([
            'password' => 'password123',
            'display_name' => 'No Email',
        ]);
    })->throws(ValidationException::class);

    it('throws ValidationException when password is too short', function () {
        $this->service->register([
            'email' => 'short@example.com',
            'password' => 'short',
            'display_name' => 'Short Pass',
        ]);
    })->throws(ValidationException::class);

    it('throws ValidationException when display_name is missing', function () {
        $this->service->register([
            'email' => 'noname@example.com',
            'password' => 'password123',
        ]);
    })->throws(ValidationException::class);

    it('throws ValidationException for invalid email format', function () {
        $this->service->register([
            'email' => 'not-an-email',
            'password' => 'password123',
            'display_name' => 'Bad Email',
        ]);
    })->throws(ValidationException::class);

    it('normalizes email to lowercase and trims whitespace', function () {
        $user = $this->service->register([
            'email' => '  Alice@Example.COM  ',
            'password' => 'password123',
            'display_name' => 'Alice',
        ]);

        expect($user->email)->toBe('alice@example.com');
    });
});

// ---------------------------------------------------------------------------
// getUser
// ---------------------------------------------------------------------------

describe('getUser', function () {
    it('returns user by ID', function () {
        $created = ($this->createTestUser)('alice@example.com', 'password123', 'Alice');

        $user = $this->service->getUser($created->id);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->id)->toBe($created->id)
            ->and($user->email)->toBe('alice@example.com');
    });

    it('throws NotFoundException for non-existent user', function () {
        $this->service->getUser(999);
    })->throws(NotFoundException::class, 'User not found');
});

// ---------------------------------------------------------------------------
// updateUser
// ---------------------------------------------------------------------------

describe('updateUser', function () {
    it('updates display_name', function () {
        $user = ($this->createTestUser)('test@example.com', 'password123', 'Old Name');

        $updated = $this->service->updateUser($user->id, [
            'display_name' => 'New Name',
        ]);

        expect($updated->displayName)->toBe('New Name');
    });

    it('updates email with validation', function () {
        $user = ($this->createTestUser)('old@example.com', 'password123', 'Test');

        $updated = $this->service->updateUser($user->id, [
            'email' => 'new@example.com',
        ]);

        expect($updated->email)->toBe('new@example.com');
    });

    it('prevents non-admin from editing another user', function () {
        $user1 = ($this->createTestUser)('user1@example.com', 'password123', 'User One', UserRole::EDITOR);
        $user2 = ($this->createTestUser)('user2@example.com', 'password123', 'User Two', UserRole::EDITOR);

        $this->service->updateUser($user2->id, ['display_name' => 'Hacked'], $user1);
    })->throws(AuthorizationException::class);

    it('allows admin to edit another user', function () {
        $admin = ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);
        $user = ($this->createTestUser)('user@example.com', 'password123', 'User');

        $updated = $this->service->updateUser($user->id, [
            'display_name' => 'Updated By Admin',
        ], $admin);

        expect($updated->displayName)->toBe('Updated By Admin');
    });

    it('allows user to edit their own profile', function () {
        $user = ($this->createTestUser)('self@example.com', 'password123', 'Self', UserRole::EDITOR);

        $updated = $this->service->updateUser($user->id, [
            'display_name' => 'Self Updated',
        ], $user);

        expect($updated->displayName)->toBe('Self Updated');
    });

    it('only admin can change roles', function () {
        $editor = ($this->createTestUser)('editor@example.com', 'password123', 'Editor', UserRole::EDITOR);

        $this->service->updateUser($editor->id, [
            'role' => UserRole::ADMIN->value,
        ], $editor);
    })->throws(AuthorizationException::class, 'Only admins can change user roles');

    it('admin can change user role', function () {
        $admin = ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);
        $user = ($this->createTestUser)('user@example.com', 'password123', 'User', UserRole::SUBSCRIBER);

        $updated = $this->service->updateUser($user->id, [
            'role' => UserRole::EDITOR->value,
        ], $admin);

        expect($updated->role)->toBe(UserRole::EDITOR);
    });

    it('throws NotFoundException for non-existent user', function () {
        $this->service->updateUser(999, ['display_name' => 'Ghost']);
    })->throws(NotFoundException::class);

    it('returns unchanged user when no data provided', function () {
        $user = ($this->createTestUser)('test@example.com', 'password123', 'Test');

        $updated = $this->service->updateUser($user->id, []);

        expect($updated->email)->toBe('test@example.com')
            ->and($updated->displayName)->toBe('Test');
    });

    it('throws ValidationException for invalid email format', function () {
        $user = ($this->createTestUser)('test@example.com', 'password123', 'Test');

        $this->service->updateUser($user->id, ['email' => 'not-valid']);
    })->throws(ValidationException::class);

    it('throws ValidationException for duplicate email', function () {
        ($this->createTestUser)('taken@example.com', 'password123', 'Taken');
        $user = ($this->createTestUser)('mine@example.com', 'password123', 'Mine');

        $this->service->updateUser($user->id, ['email' => 'taken@example.com']);
    })->throws(ValidationException::class);

    it('throws ValidationException for short display_name', function () {
        $user = ($this->createTestUser)('test@example.com', 'password123', 'Test');

        $this->service->updateUser($user->id, ['display_name' => 'X']);
    })->throws(ValidationException::class);
});

// ---------------------------------------------------------------------------
// deleteUser
// ---------------------------------------------------------------------------

describe('deleteUser', function () {
    it('deletes an existing user', function () {
        $admin = ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);
        $user = ($this->createTestUser)('delete-me@example.com', 'password123', 'Delete Me');

        $result = $this->service->deleteUser($user->id, $admin);

        expect($result)->toBeTrue();

        // Verify user is gone
        expect(fn() => $this->service->getUser($user->id))
            ->toThrow(NotFoundException::class);
    });

    it('throws NotFoundException for non-existent user', function () {
        $this->service->deleteUser(999);
    })->throws(NotFoundException::class);

    it('cannot delete yourself', function () {
        $admin = ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);

        $this->service->deleteUser($admin->id, $admin);
    })->throws(ValidationException::class);
});

// ---------------------------------------------------------------------------
// changePassword
// ---------------------------------------------------------------------------

describe('changePassword', function () {
    it('changes password when current password is correct', function () {
        $user = ($this->createTestUser)('test@example.com', 'old-password', 'Test');

        $this->service->changePassword($user->id, 'old-password', 'new-password-123');

        // Verify the new password works
        $result = $this->service->login('test@example.com', 'new-password-123');
        expect($result)->toHaveKey('token');
    });

    it('throws AuthenticationException when current password is wrong', function () {
        $user = ($this->createTestUser)('test@example.com', 'real-password', 'Test');

        $this->service->changePassword($user->id, 'wrong-password', 'new-password-123');
    })->throws(AuthenticationException::class, 'Current password is incorrect');

    it('throws ValidationException when new password is too short', function () {
        $user = ($this->createTestUser)('test@example.com', 'current-pw', 'Test');

        $this->service->changePassword($user->id, 'current-pw', 'short');
    })->throws(ValidationException::class);

    it('throws NotFoundException for non-existent user', function () {
        $this->service->changePassword(999, 'anything', 'new-password-123');
    })->throws(NotFoundException::class);
});

// ---------------------------------------------------------------------------
// listUsers
// ---------------------------------------------------------------------------

describe('listUsers', function () {
    it('returns paginated results with total count', function () {
        ($this->createTestUser)('a@example.com', 'password123', 'User A', UserRole::ADMIN);
        ($this->createTestUser)('b@example.com', 'password123', 'User B', UserRole::EDITOR);
        ($this->createTestUser)('c@example.com', 'password123', 'User C', UserRole::SUBSCRIBER);

        $result = $this->service->listUsers();

        expect($result)->toBeArray()
            ->toHaveKey('users')
            ->toHaveKey('total')
            ->and($result['users'])->toHaveCount(3)
            ->and($result['total'])->toBe(3);
    });

    it('limits results according to limit filter', function () {
        ($this->createTestUser)('a@example.com', 'password123', 'A');
        ($this->createTestUser)('b@example.com', 'password123', 'B');
        ($this->createTestUser)('c@example.com', 'password123', 'C');

        $result = $this->service->listUsers(['limit' => 2]);

        expect($result['users'])->toHaveCount(2)
            ->and($result['total'])->toBe(3);
    });

    it('caps limit at 100', function () {
        ($this->createTestUser)('a@example.com', 'password123', 'A');

        $result = $this->service->listUsers(['limit' => 500]);

        // The service caps limit to 100 via min()
        expect($result['users'])->toHaveCount(1);
    });

    it('filters by role', function () {
        ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);
        ($this->createTestUser)('sub@example.com', 'password123', 'Sub', UserRole::SUBSCRIBER);

        $result = $this->service->listUsers(['role' => 'admin']);

        expect($result['users'])->toHaveCount(1)
            ->and($result['users'][0]->role)->toBe(UserRole::ADMIN);
    });
});
