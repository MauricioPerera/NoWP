<?php

require_once BASE_PATH . '/src/Theme/helpers.php';

it('generates theme URL', function () {
    $url = theme_url('css/style.css');
    
    expect($url)->toContain('/themes/')
        ->and($url)->toContain('css/style.css');
});

it('generates asset URL without version', function () {
    $url = asset_url('css/app.css');
    
    expect($url)->toBe('/css/app.css');
});

it('generates asset URL with version hash', function () {
    // Create a test file
    $testFile = BASE_PATH . '/public/test-asset.css';
    file_put_contents($testFile, 'body { color: red; }');
    
    $url = asset_url('test-asset.css', true);
    
    expect($url)->toContain('/test-asset.css?v=')
        ->and($url)->toMatch('/test-asset\.css\?v=[a-f0-9]{8}/');
    
    // Cleanup
    unlink($testFile);
});

it('generates site URL', function () {
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['HTTPS'] = 'on';
    
    $url = site_url();
    
    expect($url)->toBe('https://example.com/');
});

it('generates site URL with path', function () {
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['HTTPS'] = 'on';
    
    $url = site_url('blog/post-1');
    
    expect($url)->toBe('https://example.com/blog/post-1');
});

it('escapes HTML entities', function () {
    $escaped = e('<script>alert("xss")</script>');
    
    expect($escaped)->toBe('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
});

it('formats date from DateTime', function () {
    $date = new DateTime('2026-01-15');
    $formatted = format_date($date);
    
    expect($formatted)->toBe('January 15, 2026');
});

it('formats date from string', function () {
    $formatted = format_date('2026-01-15');
    
    expect($formatted)->toBe('January 15, 2026');
});

it('formats date with custom format', function () {
    $date = new DateTime('2026-01-15');
    $formatted = format_date($date, 'Y-m-d');
    
    expect($formatted)->toBe('2026-01-15');
});

it('generates excerpt from content', function () {
    $content = 'This is a long piece of content that should be truncated to a shorter excerpt for display purposes.';
    $result = excerpt($content, 50);
    
    expect($result)->toContain('...')
        ->and(strlen($result))->toBeLessThanOrEqual(53); // 50 + '...'
});

it('does not truncate short content', function () {
    $content = 'Short content';
    $result = excerpt($content, 50);
    
    expect($result)->toBe('Short content')
        ->and($result)->not->toContain('...');
});

it('strips HTML tags from excerpt', function () {
    $content = '<p>This is <strong>HTML</strong> content</p>';
    $result = excerpt($content, 50);
    
    expect($result)->not->toContain('<p>')
        ->and($result)->not->toContain('<strong>')
        ->and($result)->toContain('This is HTML content');
});

it('pluralizes singular form', function () {
    $result = pluralize(1, 'item');
    
    expect($result)->toBe('1 item');
});

it('pluralizes plural form', function () {
    $result = pluralize(5, 'item');
    
    expect($result)->toBe('5 items');
});

it('pluralizes with custom plural', function () {
    $result = pluralize(2, 'person', 'people');
    
    expect($result)->toBe('2 people');
});

it('gets current URL', function () {
    $_SERVER['HTTP_HOST'] = 'example.com';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REQUEST_URI'] = '/blog/post-1';
    
    $url = current_url();
    
    expect($url)->toBe('https://example.com/blog/post-1');
});

it('checks if path is active', function () {
    $_SERVER['REQUEST_URI'] = '/blog/post-1';
    
    expect(is_active('/blog'))->toBeTrue()
        ->and(is_active('/blog/post-1'))->toBeTrue()
        ->and(is_active('/about'))->toBeFalse();
});

it('gets theme config value', function () {
    global $themeManager;
    $themeManager = new \Framework\Theme\ThemeManager(BASE_PATH . '/themes');
    $themeManager->loadTheme('default');
    
    expect(theme_config('name'))->toBe('Default Theme')
        ->and(theme_config('version'))->toBe('1.0.0');
});

it('returns default for missing theme config', function () {
    global $themeManager;
    $themeManager = new \Framework\Theme\ThemeManager(BASE_PATH . '/themes');
    $themeManager->loadTheme('default');
    
    expect(theme_config('missing', 'default'))->toBe('default');
});

it('handles missing theme manager', function () {
    global $themeManager;
    $themeManager = null;
    
    expect(theme_config('name', 'default'))->toBe('default');
});
