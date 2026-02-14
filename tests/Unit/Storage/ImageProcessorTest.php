<?php

/**
 * Image Processor Unit Tests
 * 
 * Tests for ImageProcessor including resize, crop, and thumbnail generation.
 */

declare(strict_types=1);

use Framework\Storage\ImageProcessor;

beforeEach(function () {
    $this->testImagePath = __DIR__ . '/../../fixtures/test-image.jpg';
    $this->testOutputDir = __DIR__ . '/../../fixtures/processed';
    
    // Create output directory
    if (!is_dir($this->testOutputDir)) {
        mkdir($this->testOutputDir, 0755, true);
    }
    
    // Create a test image if it doesn't exist
    if (!file_exists($this->testImagePath)) {
        createTestImage($this->testImagePath);
    }
    
    $this->processor = new ImageProcessor();
});

afterEach(function () {
    // Clean up processed images
    if (is_dir($this->testOutputDir)) {
        $files = glob($this->testOutputDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->testOutputDir);
    }
});

// Helper function to create a test image
function createTestImage(string $path): void
{
    $width = 800;
    $height = 600;
    
    $image = imagecreatetruecolor($width, $height);
    
    // Fill with a color
    $blue = imagecolorallocate($image, 0, 0, 255);
    imagefill($image, 0, 0, $blue);
    
    // Add some text
    $white = imagecolorallocate($image, 255, 255, 255);
    imagestring($image, 5, 10, 10, 'Test Image', $white);
    
    // Save as JPEG
    imagejpeg($image, $path, 90);
    imagedestroy($image);
}

it('resizes an image maintaining aspect ratio', function () {
    $outputPath = $this->testOutputDir . '/resized.jpg';
    
    // Copy test image to output dir for processing
    copy($this->testImagePath, $outputPath);
    
    $resized = $this->processor->resize($outputPath, 400, 300);
    
    expect(file_exists($resized))->toBeTrue();
    
    $dimensions = $this->processor->getDimensions($resized);
    expect($dimensions['width'])->toBeLessThanOrEqual(400)
        ->and($dimensions['height'])->toBeLessThanOrEqual(300);
});

it('resizes an image without maintaining aspect ratio', function () {
    $outputPath = $this->testOutputDir . '/resized.jpg';
    copy($this->testImagePath, $outputPath);
    
    $resized = $this->processor->resize($outputPath, 400, 400, false);
    
    expect(file_exists($resized))->toBeTrue();
    
    $dimensions = $this->processor->getDimensions($resized);
    expect($dimensions['width'])->toBe(400)
        ->and($dimensions['height'])->toBe(400);
});

it('crops an image', function () {
    $outputPath = $this->testOutputDir . '/original.jpg';
    copy($this->testImagePath, $outputPath);
    
    $cropped = $this->processor->crop($outputPath, 200, 200);
    
    expect(file_exists($cropped))->toBeTrue();
    
    $dimensions = $this->processor->getDimensions($cropped);
    expect($dimensions['width'])->toBe(200)
        ->and($dimensions['height'])->toBe(200);
});

it('generates thumbnails in default sizes', function () {
    $outputPath = $this->testOutputDir . '/original.jpg';
    copy($this->testImagePath, $outputPath);
    
    $thumbnails = $this->processor->generateThumbnails($outputPath);
    
    expect($thumbnails)->toBeArray()
        ->and($thumbnails)->toHaveKey('thumbnail')
        ->and($thumbnails)->toHaveKey('medium')
        ->and($thumbnails)->toHaveKey('large');
    
    foreach ($thumbnails as $path) {
        expect(file_exists($path))->toBeTrue();
    }
});

it('generates thumbnails in custom sizes', function () {
    $outputPath = $this->testOutputDir . '/original.jpg';
    copy($this->testImagePath, $outputPath);
    
    $customSizes = [
        'small' => ['width' => 100, 'height' => 100],
        'custom' => ['width' => 250, 'height' => 250],
    ];
    
    $thumbnails = $this->processor->generateThumbnails($outputPath, $customSizes);
    
    expect($thumbnails)->toHaveKey('small')
        ->and($thumbnails)->toHaveKey('custom')
        ->and(file_exists($thumbnails['small']))->toBeTrue()
        ->and(file_exists($thumbnails['custom']))->toBeTrue();
});

it('gets image dimensions', function () {
    $dimensions = $this->processor->getDimensions($this->testImagePath);
    
    expect($dimensions)->toHaveKey('width')
        ->and($dimensions)->toHaveKey('height')
        ->and($dimensions['width'])->toBe(800)
        ->and($dimensions['height'])->toBe(600);
});

it('validates if file is an image', function () {
    expect($this->processor->isValidImage($this->testImagePath))->toBeTrue();
    
    // Create a non-image file
    $textFile = $this->testOutputDir . '/not-image.txt';
    file_put_contents($textFile, 'This is not an image');
    
    expect($this->processor->isValidImage($textFile))->toBeFalse();
});

it('throws exception when resizing non-existent file', function () {
    expect(fn() => $this->processor->resize('/non/existent/file.jpg', 100, 100))
        ->toThrow(RuntimeException::class, 'Image file not found');
});

it('throws exception when cropping non-existent file', function () {
    expect(fn() => $this->processor->crop('/non/existent/file.jpg', 100, 100))
        ->toThrow(RuntimeException::class, 'Image file not found');
});

it('throws exception when generating thumbnails for non-existent file', function () {
    expect(fn() => $this->processor->generateThumbnails('/non/existent/file.jpg'))
        ->toThrow(RuntimeException::class, 'Image file not found');
});

it('generates correct filename for resized image', function () {
    $outputPath = $this->testOutputDir . '/test.jpg';
    copy($this->testImagePath, $outputPath);
    
    $resized = $this->processor->resize($outputPath, 300, 200);
    
    expect($resized)->toContain('test-300x200.jpg');
});

it('generates correct filename for cropped image', function () {
    $outputPath = $this->testOutputDir . '/test.jpg';
    copy($this->testImagePath, $outputPath);
    
    $cropped = $this->processor->crop($outputPath, 200, 200);
    
    expect($cropped)->toContain('test-200x200-cropped.jpg');
});

it('generates correct filenames for thumbnails', function () {
    $outputPath = $this->testOutputDir . '/test.jpg';
    copy($this->testImagePath, $outputPath);
    
    $thumbnails = $this->processor->generateThumbnails($outputPath);
    
    expect($thumbnails['thumbnail'])->toContain('test-150x150.jpg')
        ->and($thumbnails['medium'])->toContain('test-300x300.jpg')
        ->and($thumbnails['large'])->toContain('test-1024x1024.jpg');
});

it('handles different image formats', function () {
    // Create PNG test image
    $pngPath = $this->testOutputDir . '/test.png';
    $image = imagecreatetruecolor(400, 300);
    $red = imagecolorallocate($image, 255, 0, 0);
    imagefill($image, 0, 0, $red);
    imagepng($image, $pngPath);
    imagedestroy($image);
    
    expect($this->processor->isValidImage($pngPath))->toBeTrue();
    
    $dimensions = $this->processor->getDimensions($pngPath);
    expect($dimensions['width'])->toBe(400)
        ->and($dimensions['height'])->toBe(300);
});

it('creates thumbnails that fit within specified dimensions', function () {
    $outputPath = $this->testOutputDir . '/original.jpg';
    copy($this->testImagePath, $outputPath);
    
    $thumbnails = $this->processor->generateThumbnails($outputPath, [
        'small' => ['width' => 100, 'height' => 100]
    ]);
    
    $dimensions = $this->processor->getDimensions($thumbnails['small']);
    
    // Should fit within 100x100 while maintaining aspect ratio
    expect($dimensions['width'])->toBeLessThanOrEqual(100)
        ->and($dimensions['height'])->toBeLessThanOrEqual(100);
});
