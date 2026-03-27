<?php

use ChimeraNoWP\Theme\ThemeManager;

beforeEach(function () {
    $this->themesPath = BASE_PATH . '/themes';
    $this->manager = new ThemeManager($this->themesPath);
});

it('loads a theme successfully', function () {
    $result = $this->manager->loadTheme('default');
    
    expect($result)->toBeTrue()
        ->and($this->manager->getActiveTheme())->toBe('default');
});

it('returns false when loading non-existent theme', function () {
    $result = $this->manager->loadTheme('nonexistent');
    
    expect($result)->toBeFalse()
        ->and($this->manager->getActiveTheme())->toBeNull();
});

it('renders a template with data', function () {
    $this->manager->loadTheme('default');
    
    $output = $this->manager->renderTemplate('index', [
        'title' => 'Test Page',
        'content' => '<p>Test content</p>'
    ]);
    
    expect($output)->toContain('Test Page')
        ->and($output)->toContain('<p>Test content</p>')
        ->and($output)->toContain('<!DOCTYPE html>');
});

it('throws exception when rendering non-existent template', function () {
    $this->manager->loadTheme('default');
    
    $this->manager->renderTemplate('nonexistent');
})->throws(\RuntimeException::class, "Template 'nonexistent' not found");

it('checks if template exists', function () {
    $this->manager->loadTheme('default');
    
    expect($this->manager->templateExists('index'))->toBeTrue()
        ->and($this->manager->templateExists('single'))->toBeTrue()
        ->and($this->manager->templateExists('404'))->toBeTrue()
        ->and($this->manager->templateExists('nonexistent'))->toBeFalse();
});

it('gets template path', function () {
    $this->manager->loadTheme('default');
    
    $path = $this->manager->getTemplatePath('index');
    
    expect($path)->toContain('themes/default/index.php')
        ->and(file_exists($path))->toBeTrue();
});

it('returns null for non-existent template path', function () {
    $this->manager->loadTheme('default');
    
    $path = $this->manager->getTemplatePath('nonexistent');
    
    expect($path)->toBeNull();
});

it('loads theme configuration', function () {
    $this->manager->loadTheme('default');
    
    $config = $this->manager->getConfig();
    
    expect($config)->toBeArray()
        ->and($config['name'])->toBe('Default Theme')
        ->and($config['version'])->toBe('1.0.0');
});

it('gets specific configuration value', function () {
    $this->manager->loadTheme('default');
    
    expect($this->manager->getConfig('name'))->toBe('Default Theme')
        ->and($this->manager->getConfig('version'))->toBe('1.0.0')
        ->and($this->manager->getConfig('nonexistent'))->toBeNull();
});

it('handles templates without .php extension', function () {
    $this->manager->loadTheme('default');
    
    $output = $this->manager->renderTemplate('index', ['title' => 'Test']);
    
    expect($output)->toContain('Test');
});

it('handles templates with .php extension', function () {
    $this->manager->loadTheme('default');
    
    $output = $this->manager->renderTemplate('index.php', ['title' => 'Test']);
    
    expect($output)->toContain('Test');
});

it('escapes HTML in template data', function () {
    $this->manager->loadTheme('default');
    
    $output = $this->manager->renderTemplate('index', [
        'title' => '<script>alert("xss")</script>'
    ]);
    
    expect($output)->not->toContain('<script>')
        ->and($output)->toContain('&lt;script&gt;');
});

it('renders 404 template', function () {
    $this->manager->loadTheme('default');
    
    $output = $this->manager->renderTemplate('404');
    
    expect($output)->toContain('404')
        ->and($output)->toContain('Page Not Found');
});

it('renders single post template', function () {
    $this->manager->loadTheme('default');
    
    $content = (object)[
        'title' => 'Test Post',
        'content' => '<p>Post content</p>',
        'publishedAt' => new DateTime('2026-01-15')
    ];
    
    $output = $this->manager->renderTemplate('single', ['content' => $content]);
    
    expect($output)->toContain('Test Post')
        ->and($output)->toContain('<p>Post content</p>')
        ->and($output)->toContain('January 15, 2026');
});

it('supports parent/child theme inheritance', function () {
    // Create a test child theme
    $childPath = BASE_PATH . '/themes/child-test';
    $parentPath = BASE_PATH . '/themes/parent-test';
    
    if (!is_dir($childPath)) {
        mkdir($childPath, 0755, true);
    }
    if (!is_dir($parentPath)) {
        mkdir($parentPath, 0755, true);
    }
    
    // Create parent theme config
    file_put_contents($parentPath . '/theme.php', '<?php return ["name" => "Parent"];');
    
    // Create parent template
    file_put_contents($parentPath . '/parent-only.php', '<p>From parent</p>');
    
    // Create child theme config with parent reference
    file_put_contents($childPath . '/theme.php', '<?php return ["name" => "Child", "parent" => "parent-test"];');
    
    // Create child template
    file_put_contents($childPath . '/child-only.php', '<p>From child</p>');
    
    $this->manager->loadTheme('child-test');
    
    expect($this->manager->getActiveTheme())->toBe('child-test')
        ->and($this->manager->getParentTheme())->toBe('parent-test');
    
    // Child template should be found
    expect($this->manager->templateExists('child-only'))->toBeTrue();
    
    // Parent template should be found through inheritance
    expect($this->manager->templateExists('parent-only'))->toBeTrue();
    
    // Cleanup
    unlink($childPath . '/theme.php');
    unlink($childPath . '/child-only.php');
    unlink($parentPath . '/theme.php');
    unlink($parentPath . '/parent-only.php');
    rmdir($childPath);
    rmdir($parentPath);
});

it('child theme overrides parent template', function () {
    // Create test themes
    $childPath = BASE_PATH . '/themes/child-override';
    $parentPath = BASE_PATH . '/themes/parent-override';
    
    if (!is_dir($childPath)) {
        mkdir($childPath, 0755, true);
    }
    if (!is_dir($parentPath)) {
        mkdir($parentPath, 0755, true);
    }
    
    // Create parent template
    file_put_contents($parentPath . '/theme.php', '<?php return ["name" => "Parent"];');
    file_put_contents($parentPath . '/shared.php', '<p>From parent</p>');
    
    // Create child theme with same template
    file_put_contents($childPath . '/theme.php', '<?php return ["name" => "Child", "parent" => "parent-override"];');
    file_put_contents($childPath . '/shared.php', '<p>From child</p>');
    
    $this->manager->loadTheme('child-override');
    
    $output = $this->manager->renderTemplate('shared');
    
    // Should use child template, not parent
    expect($output)->toContain('From child')
        ->and($output)->not->toContain('From parent');
    
    // Cleanup
    unlink($childPath . '/theme.php');
    unlink($childPath . '/shared.php');
    unlink($parentPath . '/theme.php');
    unlink($parentPath . '/shared.php');
    rmdir($childPath);
    rmdir($parentPath);
});

it('falls back to default theme when template not found in active theme', function () {
    // Create a minimal theme without templates
    $minimalPath = BASE_PATH . '/themes/minimal-test';
    
    if (!is_dir($minimalPath)) {
        mkdir($minimalPath, 0755, true);
    }
    
    file_put_contents($minimalPath . '/theme.php', '<?php return ["name" => "Minimal"];');
    
    $this->manager->loadTheme('minimal-test');
    
    // Should fall back to default theme's index template
    expect($this->manager->templateExists('index'))->toBeTrue();
    
    $output = $this->manager->renderTemplate('index', ['title' => 'Fallback Test']);
    expect($output)->toContain('Fallback Test');
    
    // Cleanup
    unlink($minimalPath . '/theme.php');
    rmdir($minimalPath);
});
