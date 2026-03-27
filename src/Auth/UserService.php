<?php

declare(strict_types=1);

namespace ChimeraNoWP\Auth;

use ChimeraNoWP\Core\Exceptions\ValidationException;
use ChimeraNoWP\Core\Exceptions\NotFoundException;
use ChimeraNoWP\Core\Exceptions\AuthenticationException;

class UserService
{
    public function __construct(
        private UserRepository $repository,
        private PasswordHasher $hasher,
        private JWTManager $jwtManager
    ) {}

    /**
     * Authenticate user and return JWT token
     */
    public function login(string $email, string $password): array
    {
        $user = $this->repository->findByEmail($email);

        if (!$user || !$this->hasher->verify($password, $user->getPasswordHash())) {
            throw new AuthenticationException('Invalid email or password');
        }

        $this->repository->updateLastLogin($user->id);

        $token = $this->jwtManager->generateToken(
            $user->id,
            $user->email,
            $user->role->value
        );

        return [
            'token' => $token,
            'user' => $user->toArray(),
        ];
    }

    /**
     * Register a new user
     */
    public function register(array $data): User
    {
        $this->validateRegistration($data);

        if ($this->repository->emailExists($data['email'])) {
            throw new ValidationException('Email already in use', ['email' => 'Email already in use']);
        }

        return $this->repository->create([
            'email' => strtolower(trim($data['email'])),
            'password_hash' => $this->hasher->hash($data['password']),
            'display_name' => trim($data['display_name']),
            'role' => $data['role'] ?? UserRole::SUBSCRIBER->value,
        ]);
    }

    /**
     * Get user by ID
     */
    public function getUser(int $id): User
    {
        $user = $this->repository->find($id);
        if (!$user) {
            throw new NotFoundException('User not found');
        }
        return $user;
    }

    /**
     * List users with filters
     */
    public function listUsers(array $filters = []): array
    {
        if (isset($filters['limit'])) {
            $filters['limit'] = min((int) $filters['limit'], 100);
        }

        $users = $this->repository->findAll($filters);
        $total = $this->repository->count($filters);

        return [
            'users' => $users,
            'total' => $total,
        ];
    }

    /**
     * Update user profile
     */
    public function updateUser(int $id, array $data, ?User $actor = null): User
    {
        $user = $this->repository->find($id);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        // Non-admin users can only edit themselves
        if ($actor && $actor->role !== UserRole::ADMIN && $actor->id !== $id) {
            throw new \ChimeraNoWP\Core\Exceptions\AuthorizationException('You can only edit your own profile');
        }

        $updateData = [];

        if (isset($data['email'])) {
            $email = strtolower(trim($data['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException('Invalid email format', ['email' => 'Invalid email format']);
            }
            if ($this->repository->emailExists($email, $id)) {
                throw new ValidationException('Email already in use', ['email' => 'Email already in use']);
            }
            $updateData['email'] = $email;
        }

        if (isset($data['display_name'])) {
            $name = trim($data['display_name']);
            if (strlen($name) < 2) {
                throw new ValidationException('Display name must be at least 2 characters', ['display_name' => 'Display name must be at least 2 characters']);
            }
            $updateData['display_name'] = $name;
        }

        if (isset($data['password'])) {
            if (strlen($data['password']) < 8) {
                throw new ValidationException('Password must be at least 8 characters', ['password' => 'Password must be at least 8 characters']);
            }
            $updateData['password_hash'] = $this->hasher->hash($data['password']);
        }

        // Only admins can change roles
        if (isset($data['role'])) {
            if (!$actor || $actor->role !== UserRole::ADMIN) {
                throw new \ChimeraNoWP\Core\Exceptions\AuthorizationException('Only admins can change user roles');
            }
            $role = UserRole::tryFrom($data['role']);
            if (!$role) {
                throw new ValidationException('Invalid role', ['role' => 'Invalid role. Valid: admin, editor, author, subscriber']);
            }
            $updateData['role'] = $role->value;
        }

        if (empty($updateData)) {
            return $user;
        }

        return $this->repository->update($id, $updateData);
    }

    /**
     * Delete user
     */
    public function deleteUser(int $id, ?User $actor = null): bool
    {
        $user = $this->repository->find($id);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        // Cannot delete yourself
        if ($actor && $actor->id === $id) {
            throw new ValidationException('Cannot delete your own account', ['user' => 'Cannot delete your own account']);
        }

        return $this->repository->delete($id);
    }

    /**
     * Change user password (requires current password verification)
     */
    public function changePassword(int $id, string $currentPassword, string $newPassword): void
    {
        $user = $this->repository->find($id);
        if (!$user) {
            throw new NotFoundException('User not found');
        }

        if (!$this->hasher->verify($currentPassword, $user->getPasswordHash())) {
            throw new AuthenticationException('Current password is incorrect');
        }

        if (strlen($newPassword) < 8) {
            throw new ValidationException('New password must be at least 8 characters', ['password' => 'New password must be at least 8 characters']);
        }

        $this->repository->update($id, [
            'password_hash' => $this->hasher->hash($newPassword),
        ]);
    }

    private function validateRegistration(array $data): void
    {
        $errors = [];

        $email = isset($data['email']) ? strtolower(trim($data['email'])) : '';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }

        if (empty($data['password']) || strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if (empty($data['display_name']) || strlen(trim($data['display_name'])) < 2) {
            $errors['display_name'] = 'Display name must be at least 2 characters';
        }

        if (isset($data['role'])) {
            if (!UserRole::tryFrom($data['role'])) {
                $errors['role'] = 'Invalid role';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }
    }
}
