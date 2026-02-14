<?php

namespace Framework\Auth;

use DateTime;

class User
{
    public function __construct(
        public readonly int $id,
        public string $email,
        private string $passwordHash,
        public string $displayName,
        public UserRole $role,
        public array $meta = [],
        public readonly DateTime $createdAt = new DateTime(),
        public ?DateTime $lastLoginAt = null
    ) {}

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return $this->role->hasPermission($permission);
    }

    /**
     * Check if user can perform an action on a resource
     * 
     * @param string $action The action to check (e.g., 'update', 'delete', 'content.update')
     * @param object|null $resource The resource to check against (e.g., Content)
     */
    public function can(string $action, ?object $resource = null): bool
    {
        // If action already contains a dot, it's a full permission string
        $permission = str_contains($action, '.') ? $action : $this->buildPermission($action, $resource);

        // For resource-specific checks
        if ($resource !== null && method_exists($resource, 'getAuthorId')) {
            $isOwner = $resource->getAuthorId() === $this->id;
            
            // Authors can only edit their own content
            if ($this->role === UserRole::AUTHOR && !$isOwner) {
                return false;
            }
        }

        // Check general permission
        return $this->hasPermission($permission);
    }

    /**
     * Build permission string from action and resource
     */
    private function buildPermission(string $action, ?object $resource): string
    {
        if ($resource === null) {
            return $action;
        }

        $resourceType = $this->getResourceType($resource);
        return "{$resourceType}.{$action}";
    }

    /**
     * Get resource type from object
     */
    private function getResourceType(object $resource): string
    {
        $className = get_class($resource);
        $parts = explode('\\', $className);
        return strtolower(end($parts));
    }

    /**
     * Get password hash
     */
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    /**
     * Set password hash
     */
    public function setPasswordHash(string $hash): void
    {
        $this->passwordHash = $hash;
    }

    /**
     * Convert user to array (excluding sensitive data)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'role' => $this->role->value,
            'meta' => $this->meta,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert user to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
