<?php

namespace Framework\Auth;

enum UserRole: string
{
    case ADMIN = 'admin';
    case EDITOR = 'editor';
    case AUTHOR = 'author';
    case SUBSCRIBER = 'subscriber';

    /**
     * Get all permissions for this role
     */
    public function getPermissions(): array
    {
        return match($this) {
            self::ADMIN => [
                'content.create',
                'content.read',
                'content.update',
                'content.delete',
                'content.publish',
                'user.create',
                'user.read',
                'user.update',
                'user.delete',
                'plugin.manage',
                'settings.manage',
                'media.upload',
                'media.delete',
            ],
            self::EDITOR => [
                'content.create',
                'content.read',
                'content.update',
                'content.delete',
                'content.publish',
                'media.upload',
                'media.delete',
            ],
            self::AUTHOR => [
                'content.create',
                'content.read',
                'content.update',
                'content.publish',
                'media.upload',
            ],
            self::SUBSCRIBER => [
                'content.read',
            ],
        };
    }

    /**
     * Check if this role has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions(), true);
    }
}
