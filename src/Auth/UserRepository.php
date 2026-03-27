<?php

declare(strict_types=1);

namespace ChimeraNoWP\Auth;

use ChimeraNoWP\Database\QueryBuilder;
use ChimeraNoWP\Database\Connection;
use DateTime;

class UserRepository
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    private function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }

    public function find(int $id): ?User
    {
        $row = $this->newQuery()
            ->table('users')
            ->where('id', $id)
            ->first();

        return $row ? $this->mapToUser($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->newQuery()
            ->table('users')
            ->where('email', $email)
            ->first();

        return $row ? $this->mapToUser($row) : null;
    }

    /**
     * @param array $filters Optional: role, search, limit, offset, order_by, order_direction
     * @return User[]
     */
    public function findAll(array $filters = []): array
    {
        $query = $this->newQuery()->table('users');

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereRaw(
                '(email LIKE ? OR display_name LIKE ?)',
                ["%{$search}%", "%{$search}%"]
            );
        }

        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        if (isset($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        if (isset($filters['offset'])) {
            $query->offset((int) $filters['offset']);
        }

        return array_map(fn($row) => $this->mapToUser($row), $query->get());
    }

    public function create(array $data): User
    {
        $now = new DateTime();

        $insertData = [
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'display_name' => $data['display_name'],
            'role' => $data['role'] ?? UserRole::SUBSCRIBER->value,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'last_login_at' => null,
        ];

        $id = $this->newQuery()->table('users')->insert($insertData);

        return new User(
            id: $id,
            email: $insertData['email'],
            passwordHash: $insertData['password_hash'],
            displayName: $insertData['display_name'],
            role: UserRole::from($insertData['role']),
            meta: [],
            createdAt: $now,
            lastLoginAt: null
        );
    }

    public function update(int $id, array $data): User
    {
        $updateData = [];

        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        if (isset($data['display_name'])) {
            $updateData['display_name'] = $data['display_name'];
        }
        if (isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }
        if (isset($data['password_hash'])) {
            $updateData['password_hash'] = $data['password_hash'];
        }
        if (isset($data['last_login_at'])) {
            $updateData['last_login_at'] = $data['last_login_at'];
        }

        if (!empty($updateData)) {
            $this->newQuery()
                ->table('users')
                ->where('id', $id)
                ->update($updateData);
        }

        $user = $this->find($id);
        if (!$user) {
            throw new \RuntimeException("User not found after update");
        }

        return $user;
    }

    public function delete(int $id): bool
    {
        $affected = $this->newQuery()
            ->table('users')
            ->where('id', $id)
            ->delete();

        return $affected > 0;
    }

    public function count(array $filters = []): int
    {
        $query = $this->newQuery()->table('users');

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return $query->count();
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->newQuery()
            ->table('users')
            ->where('email', $email);

        if ($excludeId !== null) {
            $query->whereRaw('id != ?', [$excludeId]);
        }

        return $query->count() > 0;
    }

    public function updateLastLogin(int $id): void
    {
        $this->newQuery()
            ->table('users')
            ->where('id', $id)
            ->update(['last_login_at' => (new DateTime())->format('Y-m-d H:i:s')]);
    }

    private function mapToUser(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            email: $row['email'],
            passwordHash: $row['password_hash'],
            displayName: $row['display_name'],
            role: UserRole::from($row['role']),
            meta: [],
            createdAt: new DateTime($row['created_at']),
            lastLoginAt: !empty($row['last_login_at']) ? new DateTime($row['last_login_at']) : null
        );
    }
}
