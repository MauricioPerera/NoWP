<?php

use Framework\Auth\UserRole;

describe('UserRole', function () {
    it('defines all required roles', function () {
        expect(UserRole::cases())->toHaveCount(4)
            ->and(UserRole::ADMIN->value)->toBe('admin')
            ->and(UserRole::EDITOR->value)->toBe('editor')
            ->and(UserRole::AUTHOR->value)->toBe('author')
            ->and(UserRole::SUBSCRIBER->value)->toBe('subscriber');
    });

    it('admin has all permissions', function () {
        $role = UserRole::ADMIN;
        $permissions = $role->getPermissions();

        expect($permissions)->toContain('content.create')
            ->and($permissions)->toContain('content.delete')
            ->and($permissions)->toContain('user.create')
            ->and($permissions)->toContain('user.delete')
            ->and($permissions)->toContain('plugin.manage')
            ->and($permissions)->toContain('settings.manage');
    });

    it('editor has content and media permissions but not user management', function () {
        $role = UserRole::EDITOR;
        $permissions = $role->getPermissions();

        expect($permissions)->toContain('content.create')
            ->and($permissions)->toContain('content.delete')
            ->and($permissions)->toContain('content.publish')
            ->and($permissions)->toContain('media.upload')
            ->and($permissions)->not->toContain('user.create')
            ->and($permissions)->not->toContain('plugin.manage');
    });

    it('author can create and publish content but not delete', function () {
        $role = UserRole::AUTHOR;
        $permissions = $role->getPermissions();

        expect($permissions)->toContain('content.create')
            ->and($permissions)->toContain('content.read')
            ->and($permissions)->toContain('content.update')
            ->and($permissions)->toContain('content.publish')
            ->and($permissions)->not->toContain('content.delete')
            ->and($permissions)->not->toContain('user.create');
    });

    it('subscriber can only read content', function () {
        $role = UserRole::SUBSCRIBER;
        $permissions = $role->getPermissions();

        expect($permissions)->toHaveCount(1)
            ->and($permissions)->toContain('content.read')
            ->and($permissions)->not->toContain('content.create')
            ->and($permissions)->not->toContain('content.update');
    });

    it('checks if role has specific permission', function () {
        expect(UserRole::ADMIN->hasPermission('user.delete'))->toBeTrue()
            ->and(UserRole::EDITOR->hasPermission('content.publish'))->toBeTrue()
            ->and(UserRole::AUTHOR->hasPermission('content.delete'))->toBeFalse()
            ->and(UserRole::SUBSCRIBER->hasPermission('content.create'))->toBeFalse();
    });
});
