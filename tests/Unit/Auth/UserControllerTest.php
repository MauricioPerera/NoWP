<?php

/**
 * UserController Unit Tests
 *
 * Tests for UserController REST API endpoints: index, show, store, update,
 * and destroy.
 */

declare(strict_types=1);

use ChimeraNoWP\Auth\UserController;
use ChimeraNoWP\Auth\UserService;
use ChimeraNoWP\Auth\UserRepository;
use ChimeraNoWP\Auth\User;
use ChimeraNoWP\Auth\UserRole;
use ChimeraNoWP\Auth\PasswordHasher;
use ChimeraNoWP\Auth\JWTManager;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
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
    $this->jwtManager = new JWTManager('test-secret-key-for-user-ctrl', 3600);
    $this->repository = new UserRepository($this->connection);

    $this->userService = new UserService(
        $this->repository,
        $this->hasher,
        $this->jwtManager
    );

    $this->controller = new UserController($this->userService);

    // Helper: create a test user directly in the database
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

    // Helper: build a Request with getParsedBody() support
    $this->makeRequest = function (
        string $method,
        string $uri,
        array $body = [],
        array $query = [],
        ?User $user = null
    ): Request {
        $request = new class ($method, $uri, [], $query, $body) extends Request {
            public function getParsedBody(): array
            {
                return $this->getBody();
            }
        };
        if ($user !== null) {
            $request->setAttribute('user', $user);
        }
        return $request;
    };
});

// ---------------------------------------------------------------------------
// index
// ---------------------------------------------------------------------------

describe('index', function () {
    it('lists all users with default pagination', function () {
        ($this->createTestUser)('a@example.com', 'password123', 'User A', UserRole::ADMIN);
        ($this->createTestUser)('b@example.com', 'password123', 'User B', UserRole::EDITOR);
        ($this->createTestUser)('c@example.com', 'password123', 'User C', UserRole::SUBSCRIBER);

        $request = ($this->makeRequest)('GET', '/api/users');
        $response = $this->controller->index($request);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue()
            ->and($data['data']['users'])->toHaveCount(3)
            ->and($data['data']['total'])->toBe(3)
            ->and($data['data'])->toHaveKey('page')
            ->and($data['data'])->toHaveKey('per_page');
    });

    it('paginates results via query params', function () {
        ($this->createTestUser)('a@example.com', 'password123', 'A');
        ($this->createTestUser)('b@example.com', 'password123', 'B');
        ($this->createTestUser)('c@example.com', 'password123', 'C');

        $request = ($this->makeRequest)('GET', '/api/users', [], [
            'page' => '2',
            'per_page' => '2',
        ]);
        $response = $this->controller->index($request);

        $data = json_decode($response->getContent(), true);
        expect($data['data']['users'])->toHaveCount(1)
            ->and($data['data']['total'])->toBe(3)
            ->and($data['data']['page'])->toBe(2)
            ->and($data['data']['per_page'])->toBe(2);
    });

    it('filters users by role', function () {
        ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);
        ($this->createTestUser)('sub@example.com', 'password123', 'Sub', UserRole::SUBSCRIBER);

        $request = ($this->makeRequest)('GET', '/api/users', [], [
            'role' => 'admin',
        ]);
        $response = $this->controller->index($request);

        $data = json_decode($response->getContent(), true);
        expect($data['data']['users'])->toHaveCount(1)
            ->and($data['data']['users'][0]['role'])->toBe('admin');
    });

    it('searches users by name or email', function () {
        ($this->createTestUser)('alice@example.com', 'password123', 'Alice Wonderland');
        ($this->createTestUser)('bob@example.com', 'password123', 'Bob Builder');

        $request = ($this->makeRequest)('GET', '/api/users', [], [
            'search' => 'alice',
        ]);
        $response = $this->controller->index($request);

        $data = json_decode($response->getContent(), true);
        expect($data['data']['users'])->toHaveCount(1)
            ->and($data['data']['users'][0]['email'])->toBe('alice@example.com');
    });

    it('returns empty list when no users match', function () {
        $request = ($this->makeRequest)('GET', '/api/users', [], [
            'role' => 'admin',
        ]);
        $response = $this->controller->index($request);

        $data = json_decode($response->getContent(), true);
        expect($data['data']['users'])->toBeEmpty()
            ->and($data['data']['total'])->toBe(0);
    });
});

// ---------------------------------------------------------------------------
// show
// ---------------------------------------------------------------------------

describe('show', function () {
    it('returns a specific user by ID', function () {
        $user = ($this->createTestUser)('alice@example.com', 'password123', 'Alice', UserRole::EDITOR);

        $request = ($this->makeRequest)('GET', "/api/users/{$user->id}");
        $response = $this->controller->show($request, $user->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue()
            ->and($data['data']['id'])->toBe($user->id)
            ->and($data['data']['email'])->toBe('alice@example.com')
            ->and($data['data']['display_name'])->toBe('Alice')
            ->and($data['data']['role'])->toBe('editor');
    });

    it('returns 404 for non-existent user', function () {
        $request = ($this->makeRequest)('GET', '/api/users/999');
        $response = $this->controller->show($request, 999);

        expect($response->getStatusCode())->toBe(404);

        $data = json_decode($response->getContent(), true);
        expect($data['error']['code'])->toBe('USER_NOT_FOUND');
    });

    it('does not expose password hash in response', function () {
        $user = ($this->createTestUser)('alice@example.com', 'password123', 'Alice');

        $request = ($this->makeRequest)('GET', "/api/users/{$user->id}");
        $response = $this->controller->show($request, $user->id);

        $data = json_decode($response->getContent(), true);
        expect($data['data'])->not->toHaveKey('password_hash')
            ->and($data['data'])->not->toHaveKey('password');
    });
});

// ---------------------------------------------------------------------------
// store
// ---------------------------------------------------------------------------

describe('store', function () {
    it('creates a user and returns 201', function () {
        $request = ($this->makeRequest)('POST', '/api/users', [
            'email' => 'new@example.com',
            'password' => 'password123',
            'display_name' => 'New User',
            'role' => 'editor',
        ]);

        $response = $this->controller->store($request);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue()
            ->and($data['data']['email'])->toBe('new@example.com')
            ->and($data['data']['display_name'])->toBe('New User');
    });

    it('returns 422 for invalid registration data', function () {
        $request = ($this->makeRequest)('POST', '/api/users', [
            'email' => 'bad',
            'password' => 'x',
        ]);

        $response = $this->controller->store($request);

        expect($response->getStatusCode())->toBe(422);

        $data = json_decode($response->getContent(), true);
        expect($data['error']['code'])->toBe('VALIDATION_ERROR');
    });

    it('returns 422 for duplicate email', function () {
        ($this->createTestUser)('taken@example.com');

        $request = ($this->makeRequest)('POST', '/api/users', [
            'email' => 'taken@example.com',
            'password' => 'password123',
            'display_name' => 'Duplicate',
        ]);

        $response = $this->controller->store($request);

        expect($response->getStatusCode())->toBe(422);
    });
});

// ---------------------------------------------------------------------------
// update
// ---------------------------------------------------------------------------

describe('update', function () {
    it('updates user profile fields', function () {
        $admin = ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);
        $user = ($this->createTestUser)('target@example.com', 'password123', 'Old Name');

        $request = ($this->makeRequest)('PUT', "/api/users/{$user->id}", [
            'display_name' => 'New Name',
        ], [], $admin);

        $response = $this->controller->update($request, $user->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue()
            ->and($data['data']['display_name'])->toBe('New Name');
    });

    it('returns 404 for non-existent user', function () {
        $admin = ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);

        $request = ($this->makeRequest)('PUT', '/api/users/999', [
            'display_name' => 'Ghost',
        ], [], $admin);

        $response = $this->controller->update($request, 999);

        expect($response->getStatusCode())->toBe(404);
    });

    it('returns 403 when non-admin edits another user', function () {
        $editor = ($this->createTestUser)('editor@example.com', 'password123', 'Editor', UserRole::EDITOR);
        $other = ($this->createTestUser)('other@example.com', 'password123', 'Other');

        $request = ($this->makeRequest)('PUT', "/api/users/{$other->id}", [
            'display_name' => 'Hacked',
        ], [], $editor);

        $response = $this->controller->update($request, $other->id);

        expect($response->getStatusCode())->toBe(403);

        $data = json_decode($response->getContent(), true);
        expect($data['error']['code'])->toBe('FORBIDDEN');
    });

    it('allows user to update own profile', function () {
        $user = ($this->createTestUser)('self@example.com', 'password123', 'Old', UserRole::EDITOR);

        $request = ($this->makeRequest)('PUT', "/api/users/{$user->id}", [
            'display_name' => 'Updated',
        ], [], $user);

        $response = $this->controller->update($request, $user->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['data']['display_name'])->toBe('Updated');
    });

    it('returns 422 for invalid email format', function () {
        $user = ($this->createTestUser)('me@example.com', 'password123', 'Me');

        $request = ($this->makeRequest)('PUT', "/api/users/{$user->id}", [
            'email' => 'not-valid',
        ], [], $user);

        $response = $this->controller->update($request, $user->id);

        expect($response->getStatusCode())->toBe(422);
    });
});

// ---------------------------------------------------------------------------
// destroy
// ---------------------------------------------------------------------------

describe('destroy', function () {
    it('deletes a user and returns 204', function () {
        $admin = ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);
        $target = ($this->createTestUser)('target@example.com', 'password123', 'Target');

        $request = ($this->makeRequest)('DELETE', "/api/users/{$target->id}", [], [], $admin);
        $response = $this->controller->destroy($request, $target->id);

        expect($response->getStatusCode())->toBe(204);

        // Verify user is gone
        $showRequest = ($this->makeRequest)('GET', "/api/users/{$target->id}");
        $showResponse = $this->controller->show($showRequest, $target->id);
        expect($showResponse->getStatusCode())->toBe(404);
    });

    it('returns 404 for non-existent user', function () {
        $admin = ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);

        $request = ($this->makeRequest)('DELETE', '/api/users/999', [], [], $admin);
        $response = $this->controller->destroy($request, 999);

        expect($response->getStatusCode())->toBe(404);
    });

    it('returns 422 when trying to delete yourself', function () {
        $admin = ($this->createTestUser)('admin@example.com', 'password123', 'Admin', UserRole::ADMIN);

        $request = ($this->makeRequest)('DELETE', "/api/users/{$admin->id}", [], [], $admin);
        $response = $this->controller->destroy($request, $admin->id);

        expect($response->getStatusCode())->toBe(422);
    });
});
