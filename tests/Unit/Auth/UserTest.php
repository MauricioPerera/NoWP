<?php

use Framework\Auth\User;
use Framework\Auth\UserRole;

describe('User', function () {
    it('creates a user with required properties', function () {
        $user = new User(
            id: 1,
            email: 'test@example.com',
            passwordHash: 'hashed_password',
            displayName: 'Test User',
            role: UserRole::ADMIN
        );

        expect($user->id)->toBe(1)
            ->and($user->email)->toBe('test@example.com')
            ->and($user->displayName)->toBe('Test User')
            ->and($user->role)->toBe(UserRole::ADMIN);
    });

    it('checks if user has permission based on role', function () {
        $admin = new User(1, 'admin@example.com', 'hash', 'Admin', UserRole::ADMIN);
        $editor = new User(2, 'editor@example.com', 'hash', 'Editor', UserRole::EDITOR);
        $author = new User(3, 'author@example.com', 'hash', 'Author', UserRole::AUTHOR);
        $subscriber = new User(4, 'subscriber@example.com', 'hash', 'Subscriber', UserRole::SUBSCRIBER);

        expect($admin->hasPermission('user.delete'))->toBeTrue()
            ->and($editor->hasPermission('content.publish'))->toBeTrue()
            ->and($editor->hasPermission('user.delete'))->toBeFalse()
            ->and($author->hasPermission('content.create'))->toBeTrue()
            ->and($author->hasPermission('content.delete'))->toBeFalse()
            ->and($subscriber->hasPermission('content.read'))->toBeTrue()
            ->and($subscriber->hasPermission('content.create'))->toBeFalse();
    });

    it('checks if user can perform action without resource', function () {
        $admin = new User(1, 'admin@example.com', 'hash', 'Admin', UserRole::ADMIN);
        $subscriber = new User(2, 'subscriber@example.com', 'hash', 'Subscriber', UserRole::SUBSCRIBER);

        expect($admin->can('user.create'))->toBeTrue()
            ->and($subscriber->can('content.create'))->toBeFalse();
    });

    it('checks if user can perform action on owned resource', function () {
        $author = new User(1, 'author@example.com', 'hash', 'Author', UserRole::AUTHOR);
        
        // Mock resource with getAuthorId method - use a named class
        $ownedResource = new class(1) {
            public function __construct(private int $authorId) {}
            public function getAuthorId(): int {
                return $this->authorId;
            }
        };
        
        $othersResource = new class(999) {
            public function __construct(private int $authorId) {}
            public function getAuthorId(): int {
                return $this->authorId;
            }
        };

        // Use full permission string since we can't infer resource type from anonymous class
        expect($author->can('content.update', $ownedResource))->toBeTrue()
            ->and($author->can('content.update', $othersResource))->toBeFalse();
    });

    it('admin can perform action on any resource', function () {
        $admin = new User(1, 'admin@example.com', 'hash', 'Admin', UserRole::ADMIN);
        
        $othersResource = new class(999) {
            public function __construct(private int $authorId) {}
            public function getAuthorId(): int {
                return $this->authorId;
            }
        };

        expect($admin->can('content.delete', $othersResource))->toBeTrue();
    });

    it('converts user to array without sensitive data', function () {
        $user = new User(
            id: 1,
            email: 'test@example.com',
            passwordHash: 'secret_hash',
            displayName: 'Test User',
            role: UserRole::EDITOR,
            meta: ['bio' => 'Test bio']
        );

        $array = $user->toArray();

        expect($array)->toHaveKey('id')
            ->and($array)->toHaveKey('email')
            ->and($array)->toHaveKey('display_name')
            ->and($array)->toHaveKey('role')
            ->and($array)->toHaveKey('meta')
            ->and($array)->not->toHaveKey('passwordHash')
            ->and($array['role'])->toBe('editor');
    });

    it('converts user to JSON', function () {
        $user = new User(
            id: 1,
            email: 'test@example.com',
            passwordHash: 'hash',
            displayName: 'Test User',
            role: UserRole::AUTHOR
        );

        $json = $user->toJson();
        $decoded = json_decode($json, true);

        expect($decoded)->toBeArray()
            ->and($decoded['id'])->toBe(1)
            ->and($decoded['email'])->toBe('test@example.com')
            ->and($decoded['role'])->toBe('author');
    });

    it('gets and sets password hash', function () {
        $user = new User(1, 'test@example.com', 'old_hash', 'Test', UserRole::ADMIN);

        expect($user->getPasswordHash())->toBe('old_hash');

        $user->setPasswordHash('new_hash');
        expect($user->getPasswordHash())->toBe('new_hash');
    });
});
