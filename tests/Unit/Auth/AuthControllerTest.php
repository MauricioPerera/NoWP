<?php

/**
 * AuthController Unit Tests
 *
 * Tests for AuthController REST API endpoints: login, register, me, refresh,
 * and changePassword.
 */

declare(strict_types=1);

use ChimeraNoWP\Auth\AuthController;
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
    $this->jwtManager = new JWTManager('test-secret-key-for-controller-tests', 3600);
    $this->repository = new UserRepository($this->connection);

    $this->userService = new UserService(
        $this->repository,
        $this->hasher,
        $this->jwtManager
    );

    $this->controller = new AuthController($this->userService, $this->jwtManager);

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

    // Helper: build a Request whose body is accessible via getParsedBody()
    // The AuthController calls $request->getParsedBody() which is not defined
    // on the base Request. We create an anonymous subclass to provide it.
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
// login
// ---------------------------------------------------------------------------

describe('login', function () {
    it('returns token and user on valid credentials', function () {
        ($this->createTestUser)('alice@example.com', 'secret123', 'Alice');

        $request = ($this->makeRequest)('POST', '/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->controller->login($request);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toHaveKey('token')
            ->and($data['data']['user']['email'])->toBe('alice@example.com');
    });

    it('returns 400 when email is missing', function () {
        $request = ($this->makeRequest)('POST', '/api/auth/login', [
            'password' => 'secret123',
        ]);

        $response = $this->controller->login($request);

        expect($response->getStatusCode())->toBe(400);

        $data = json_decode($response->getContent(), true);
        expect($data['error']['code'])->toBe('VALIDATION_ERROR');
    });

    it('returns 400 when password is missing', function () {
        $request = ($this->makeRequest)('POST', '/api/auth/login', [
            'email' => 'alice@example.com',
        ]);

        $response = $this->controller->login($request);

        expect($response->getStatusCode())->toBe(400);
    });

    it('returns 401 for wrong credentials', function () {
        ($this->createTestUser)('alice@example.com', 'correct-password');

        $request = ($this->makeRequest)('POST', '/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'wrong-password',
        ]);

        $response = $this->controller->login($request);

        expect($response->getStatusCode())->toBe(401);

        $data = json_decode($response->getContent(), true);
        expect($data['error']['code'])->toBe('INVALID_CREDENTIALS');
    });

    it('returns 401 for non-existent email', function () {
        $request = ($this->makeRequest)('POST', '/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response = $this->controller->login($request);

        expect($response->getStatusCode())->toBe(401);
    });
});

// ---------------------------------------------------------------------------
// register
// ---------------------------------------------------------------------------

describe('register', function () {
    it('creates user and returns token with 201 status', function () {
        $request = ($this->makeRequest)('POST', '/api/auth/register', [
            'email' => 'new@example.com',
            'password' => 'password123',
            'display_name' => 'New User',
        ]);

        $response = $this->controller->register($request);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);

        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toHaveKey('token')
            ->and($data['data']['user']['email'])->toBe('new@example.com')
            ->and($data['data']['user']['role'])->toBe('subscriber');
    });

    it('forces subscriber role for public registration', function () {
        $request = ($this->makeRequest)('POST', '/api/auth/register', [
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'display_name' => 'Sneaky',
            'role' => 'admin', // attempted role escalation
        ]);

        $response = $this->controller->register($request);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['data']['user']['role'])->toBe('subscriber');
    });

    it('returns 422 with validation errors for invalid data', function () {
        $request = ($this->makeRequest)('POST', '/api/auth/register', [
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response = $this->controller->register($request);

        expect($response->getStatusCode())->toBe(422);

        $data = json_decode($response->getContent(), true);
        expect($data['error']['code'])->toBe('VALIDATION_ERROR');
    });

    it('returns 422 for duplicate email', function () {
        ($this->createTestUser)('taken@example.com');

        $request = ($this->makeRequest)('POST', '/api/auth/register', [
            'email' => 'taken@example.com',
            'password' => 'password123',
            'display_name' => 'Duplicate',
        ]);

        $response = $this->controller->register($request);

        expect($response->getStatusCode())->toBe(422);
    });
});

// ---------------------------------------------------------------------------
// me
// ---------------------------------------------------------------------------

describe('me', function () {
    it('returns authenticated user profile', function () {
        $user = ($this->createTestUser)('alice@example.com', 'password123', 'Alice', UserRole::EDITOR);

        $request = ($this->makeRequest)('GET', '/api/auth/me', [], [], $user);

        $response = $this->controller->me($request);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue()
            ->and($data['data']['email'])->toBe('alice@example.com')
            ->and($data['data']['display_name'])->toBe('Alice')
            ->and($data['data']['role'])->toBe('editor');
    });

    it('returns 401 when not authenticated', function () {
        $request = ($this->makeRequest)('GET', '/api/auth/me');

        $response = $this->controller->me($request);

        expect($response->getStatusCode())->toBe(401);

        $data = json_decode($response->getContent(), true);
        expect($data['error']['code'])->toBe('AUTHENTICATION_REQUIRED');
    });
});

// ---------------------------------------------------------------------------
// refresh
// ---------------------------------------------------------------------------

describe('refresh', function () {
    it('returns a new token for authenticated user', function () {
        $user = ($this->createTestUser)('alice@example.com', 'password123', 'Alice');

        $request = ($this->makeRequest)('POST', '/api/auth/refresh', [], [], $user);

        $response = $this->controller->refresh($request);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue()
            ->and($data['data'])->toHaveKey('token')
            ->and($data['data']['token'])->toBeString()->not->toBeEmpty()
            ->and($data['data']['user']['email'])->toBe('alice@example.com');
    });

    it('returns 401 when not authenticated', function () {
        $request = ($this->makeRequest)('POST', '/api/auth/refresh');

        $response = $this->controller->refresh($request);

        expect($response->getStatusCode())->toBe(401);
    });
});

// ---------------------------------------------------------------------------
// changePassword
// ---------------------------------------------------------------------------

describe('changePassword', function () {
    it('changes password successfully', function () {
        $user = ($this->createTestUser)('alice@example.com', 'old-password', 'Alice');

        $request = ($this->makeRequest)('POST', '/api/auth/change-password', [
            'current_password' => 'old-password',
            'new_password' => 'new-password-123',
        ], [], $user);

        $response = $this->controller->changePassword($request);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();

        // Verify new password works for login
        $loginRequest = ($this->makeRequest)('POST', '/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'new-password-123',
        ]);
        $loginResponse = $this->controller->login($loginRequest);
        expect($loginResponse->getStatusCode())->toBe(200);
    });

    it('returns 400 when fields are missing', function () {
        $user = ($this->createTestUser)('alice@example.com', 'password123', 'Alice');

        $request = ($this->makeRequest)('POST', '/api/auth/change-password', [], [], $user);

        $response = $this->controller->changePassword($request);

        expect($response->getStatusCode())->toBe(400);

        $data = json_decode($response->getContent(), true);
        expect($data['error']['code'])->toBe('VALIDATION_ERROR');
    });

    it('returns 401 when current password is wrong', function () {
        $user = ($this->createTestUser)('alice@example.com', 'real-password', 'Alice');

        $request = ($this->makeRequest)('POST', '/api/auth/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'new-password-123',
        ], [], $user);

        $response = $this->controller->changePassword($request);

        expect($response->getStatusCode())->toBe(401);

        $data = json_decode($response->getContent(), true);
        expect($data['error']['code'])->toBe('INVALID_PASSWORD');
    });

    it('returns 401 when not authenticated', function () {
        $request = ($this->makeRequest)('POST', '/api/auth/change-password', [
            'current_password' => 'old',
            'new_password' => 'new-password-123',
        ]);

        $response = $this->controller->changePassword($request);

        expect($response->getStatusCode())->toBe(401);
    });

    it('returns 422 when new password is too short', function () {
        $user = ($this->createTestUser)('alice@example.com', 'current-pw', 'Alice');

        $request = ($this->makeRequest)('POST', '/api/auth/change-password', [
            'current_password' => 'current-pw',
            'new_password' => 'short',
        ], [], $user);

        $response = $this->controller->changePassword($request);

        expect($response->getStatusCode())->toBe(422);
    });
});
