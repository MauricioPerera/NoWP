<?php

use Framework\Content\Media;

describe('Media Model', function () {
    beforeEach(function () {
        // Set up test environment - config function will use actual config files
        // The app.url is set in config/app.php
    });

    it('creates a media instance with all properties', function () {
        $media = new Media(
            id: 1,
            filename: 'test-image.jpg',
            path: '2026/01/test-image-abc123.jpg',
            mimeType: 'image/jpeg',
            size: 102400,
            width: 1920,
            height: 1080,
            thumbnails: [
                '150x150' => '2026/01/test-image-abc123-150x150.jpg',
                '300x300' => '2026/01/test-image-abc123-300x300.jpg',
            ],
            uploadedBy: 1,
            uploadedAt: new DateTime('2026-01-15 10:30:00')
        );

        expect($media->id)->toBe(1)
            ->and($media->filename)->toBe('test-image.jpg')
            ->and($media->path)->toBe('2026/01/test-image-abc123.jpg')
            ->and($media->mimeType)->toBe('image/jpeg')
            ->and($media->size)->toBe(102400)
            ->and($media->width)->toBe(1920)
            ->and($media->height)->toBe(1080)
            ->and($media->thumbnails)->toHaveCount(2)
            ->and($media->uploadedBy)->toBe(1);
    });

    it('generates correct public URL for media file', function () {
        $media = new Media(
            id: 1,
            filename: 'test-image.jpg',
            path: '2026/01/test-image-abc123.jpg',
            mimeType: 'image/jpeg',
            size: 102400,
            width: 1920,
            height: 1080,
            thumbnails: [],
            uploadedBy: 1,
            uploadedAt: new DateTime()
        );

        $url = $media->url();

        expect($url)->toBe('http://localhost/uploads/2026/01/test-image-abc123.jpg');
    });

    it('generates correct thumbnail URL for existing size', function () {
        $media = new Media(
            id: 1,
            filename: 'test-image.jpg',
            path: '2026/01/test-image-abc123.jpg',
            mimeType: 'image/jpeg',
            size: 102400,
            width: 1920,
            height: 1080,
            thumbnails: [
                '150x150' => '2026/01/test-image-abc123-150x150.jpg',
                '300x300' => '2026/01/test-image-abc123-300x300.jpg',
            ],
            uploadedBy: 1,
            uploadedAt: new DateTime()
        );

        $thumbnailUrl = $media->thumbnailUrl('150x150');

        expect($thumbnailUrl)->toBe('http://localhost/uploads/2026/01/test-image-abc123-150x150.jpg');
    });

    it('returns null for non-existent thumbnail size', function () {
        $media = new Media(
            id: 1,
            filename: 'test-image.jpg',
            path: '2026/01/test-image-abc123.jpg',
            mimeType: 'image/jpeg',
            size: 102400,
            width: 1920,
            height: 1080,
            thumbnails: [
                '150x150' => '2026/01/test-image-abc123-150x150.jpg',
            ],
            uploadedBy: 1,
            uploadedAt: new DateTime()
        );

        $thumbnailUrl = $media->thumbnailUrl('500x500');

        expect($thumbnailUrl)->toBeNull();
    });

    it('handles media without dimensions (non-image files)', function () {
        $media = new Media(
            id: 2,
            filename: 'document.pdf',
            path: '2026/01/document-xyz789.pdf',
            mimeType: 'application/pdf',
            size: 204800,
            width: null,
            height: null,
            thumbnails: [],
            uploadedBy: 1,
            uploadedAt: new DateTime()
        );

        expect($media->width)->toBeNull()
            ->and($media->height)->toBeNull()
            ->and($media->thumbnails)->toBeEmpty();
    });

    it('converts media to array with all fields', function () {
        $uploadedAt = new DateTime('2026-01-15 10:30:00');
        $media = new Media(
            id: 1,
            filename: 'test-image.jpg',
            path: '2026/01/test-image-abc123.jpg',
            mimeType: 'image/jpeg',
            size: 102400,
            width: 1920,
            height: 1080,
            thumbnails: [
                '150x150' => '2026/01/test-image-abc123-150x150.jpg',
            ],
            uploadedBy: 1,
            uploadedAt: $uploadedAt
        );

        $array = $media->toArray();

        expect($array)->toHaveKeys([
            'id', 'filename', 'path', 'mime_type', 'size',
            'width', 'height', 'thumbnails', 'uploaded_by', 'uploaded_at', 'url'
        ])
            ->and($array['id'])->toBe(1)
            ->and($array['filename'])->toBe('test-image.jpg')
            ->and($array['mime_type'])->toBe('image/jpeg')
            ->and($array['uploaded_at'])->toBe('2026-01-15 10:30:00')
            ->and($array['url'])->toBe('http://localhost/uploads/2026/01/test-image-abc123.jpg');
    });

    it('converts media to valid JSON', function () {
        $media = new Media(
            id: 1,
            filename: 'test-image.jpg',
            path: '2026/01/test-image-abc123.jpg',
            mimeType: 'image/jpeg',
            size: 102400,
            width: 1920,
            height: 1080,
            thumbnails: [],
            uploadedBy: 1,
            uploadedAt: new DateTime('2026-01-15 10:30:00')
        );

        $json = $media->toJson();
        $decoded = json_decode($json, true);

        expect($decoded)->toBeArray()
            ->and($decoded['id'])->toBe(1)
            ->and($decoded['filename'])->toBe('test-image.jpg');
    });

    it('handles paths with leading slashes correctly', function () {
        $media = new Media(
            id: 1,
            filename: 'test-image.jpg',
            path: '/2026/01/test-image-abc123.jpg', // Leading slash
            mimeType: 'image/jpeg',
            size: 102400,
            width: 1920,
            height: 1080,
            thumbnails: [
                '150x150' => '/2026/01/test-image-abc123-150x150.jpg', // Leading slash
            ],
            uploadedBy: 1,
            uploadedAt: new DateTime()
        );

        $url = $media->url();
        $thumbnailUrl = $media->thumbnailUrl('150x150');

        // Should not have double slashes
        expect($url)->toBe('http://localhost/uploads/2026/01/test-image-abc123.jpg')
            ->and($thumbnailUrl)->toBe('http://localhost/uploads/2026/01/test-image-abc123-150x150.jpg');
    });
});
