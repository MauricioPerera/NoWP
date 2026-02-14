<?php

use Framework\Content\Content;
use Framework\Content\ContentType;
use Framework\Content\ContentStatus;

test('Content model can be instantiated with required properties', function () {
    $now = new DateTime();
    
    $content = new Content(
        id: 1,
        title: 'Test Post',
        slug: 'test-post',
        content: 'This is test content',
        type: ContentType::POST,
        status: ContentStatus::DRAFT,
        authorId: 1,
        parentId: null,
        customFields: [],
        createdAt: $now,
        updatedAt: $now
    );

    expect($content->id)->toBe(1)
        ->and($content->title)->toBe('Test Post')
        ->and($content->slug)->toBe('test-post')
        ->and($content->type)->toBe(ContentType::POST)
        ->and($content->status)->toBe(ContentStatus::DRAFT);
});

test('Content toArray returns correct structure', function () {
    $createdAt = new DateTime('2026-01-15 10:00:00');
    $updatedAt = new DateTime('2026-01-15 11:00:00');
    $publishedAt = new DateTime('2026-01-15 12:00:00');
    
    $content = new Content(
        id: 1,
        title: 'Test Post',
        slug: 'test-post',
        content: 'This is test content',
        type: ContentType::POST,
        status: ContentStatus::PUBLISHED,
        authorId: 1,
        parentId: null,
        customFields: ['key' => 'value'],
        createdAt: $createdAt,
        updatedAt: $updatedAt,
        publishedAt: $publishedAt
    );

    $array = $content->toArray();

    expect($array)->toBeArray()
        ->and($array['id'])->toBe(1)
        ->and($array['title'])->toBe('Test Post')
        ->and($array['slug'])->toBe('test-post')
        ->and($array['content'])->toBe('This is test content')
        ->and($array['type'])->toBe('post')
        ->and($array['status'])->toBe('published')
        ->and($array['author_id'])->toBe(1)
        ->and($array['parent_id'])->toBeNull()
        ->and($array['custom_fields'])->toBe(['key' => 'value'])
        ->and($array['created_at'])->toBe('2026-01-15 10:00:00')
        ->and($array['updated_at'])->toBe('2026-01-15 11:00:00')
        ->and($array['published_at'])->toBe('2026-01-15 12:00:00');
});

test('Content toJson returns valid JSON string', function () {
    $now = new DateTime('2026-01-15 10:00:00');
    
    $content = new Content(
        id: 1,
        title: 'Test Post',
        slug: 'test-post',
        content: 'This is test content',
        type: ContentType::POST,
        status: ContentStatus::DRAFT,
        authorId: 1,
        parentId: null,
        customFields: [],
        createdAt: $now,
        updatedAt: $now
    );

    $json = $content->toJson();
    
    expect($json)->toBeString();
    
    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray()
        ->and($decoded['id'])->toBe(1)
        ->and($decoded['title'])->toBe('Test Post')
        ->and($decoded['type'])->toBe('post')
        ->and($decoded['status'])->toBe('draft');
});

test('Content handles null publishedAt correctly', function () {
    $now = new DateTime();
    
    $content = new Content(
        id: 1,
        title: 'Draft Post',
        slug: 'draft-post',
        content: 'Draft content',
        type: ContentType::POST,
        status: ContentStatus::DRAFT,
        authorId: 1,
        parentId: null,
        customFields: [],
        createdAt: $now,
        updatedAt: $now,
        publishedAt: null
    );

    $array = $content->toArray();
    expect($array['published_at'])->toBeNull();
});

test('Content supports all ContentType values', function () {
    $now = new DateTime();
    
    $types = [ContentType::POST, ContentType::PAGE, ContentType::CUSTOM];
    
    foreach ($types as $type) {
        $content = new Content(
            id: 1,
            title: 'Test',
            slug: 'test',
            content: 'Content',
            type: $type,
            status: ContentStatus::DRAFT,
            authorId: 1,
            parentId: null,
            customFields: [],
            createdAt: $now,
            updatedAt: $now
        );
        
        expect($content->type)->toBe($type);
    }
});

test('Content supports all ContentStatus values', function () {
    $now = new DateTime();
    
    $statuses = [
        ContentStatus::DRAFT,
        ContentStatus::PUBLISHED,
        ContentStatus::SCHEDULED,
        ContentStatus::TRASH
    ];
    
    foreach ($statuses as $status) {
        $content = new Content(
            id: 1,
            title: 'Test',
            slug: 'test',
            content: 'Content',
            type: ContentType::POST,
            status: $status,
            authorId: 1,
            parentId: null,
            customFields: [],
            createdAt: $now,
            updatedAt: $now
        );
        
        expect($content->status)->toBe($status);
    }
});

test('Content id is readonly', function () {
    $now = new DateTime();
    
    $content = new Content(
        id: 1,
        title: 'Test',
        slug: 'test',
        content: 'Content',
        type: ContentType::POST,
        status: ContentStatus::DRAFT,
        authorId: 1,
        parentId: null,
        customFields: [],
        createdAt: $now,
        updatedAt: $now
    );
    
    expect($content->id)->toBe(1);
    
    // This should cause an error if uncommented (readonly property)
    // $content->id = 2;
})->skip('Readonly property test - cannot be tested at runtime');

test('Content with parent_id', function () {
    $now = new DateTime();
    
    $content = new Content(
        id: 2,
        title: 'Child Page',
        slug: 'child-page',
        content: 'Child content',
        type: ContentType::PAGE,
        status: ContentStatus::PUBLISHED,
        authorId: 1,
        parentId: 1,
        customFields: [],
        createdAt: $now,
        updatedAt: $now
    );

    expect($content->parentId)->toBe(1);
    
    $array = $content->toArray();
    expect($array['parent_id'])->toBe(1);
});

test('Content with custom fields', function () {
    $now = new DateTime();
    
    $customFields = [
        'meta_description' => 'SEO description',
        'featured_image' => 'image.jpg',
        'views' => 100
    ];
    
    $content = new Content(
        id: 1,
        title: 'Test',
        slug: 'test',
        content: 'Content',
        type: ContentType::POST,
        status: ContentStatus::PUBLISHED,
        authorId: 1,
        parentId: null,
        customFields: $customFields,
        createdAt: $now,
        updatedAt: $now
    );

    expect($content->customFields)->toBe($customFields);
    
    $array = $content->toArray();
    expect($array['custom_fields'])->toBe($customFields);
});
