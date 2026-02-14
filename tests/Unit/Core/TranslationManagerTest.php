<?php

use Framework\Core\TranslationManager;

beforeEach(function () {
    $this->translationsPath = __DIR__ . '/../../fixtures/translations';
    $this->manager = new TranslationManager($this->translationsPath);
    
    // Create test translation files
    if (!is_dir($this->translationsPath . '/en')) {
        mkdir($this->translationsPath . '/en', 0755, true);
    }
    if (!is_dir($this->translationsPath . '/es')) {
        mkdir($this->translationsPath . '/es', 0755, true);
    }
    
    // English translations
    file_put_contents($this->translationsPath . '/en/messages.php', '<?php return [
        "welcome" => "Welcome",
        "goodbye" => "Goodbye",
        "greeting" => "Hello, :name!",
        "nested" => [
            "key" => "Nested value"
        ]
    ];');
    
    // Spanish translations
    file_put_contents($this->translationsPath . '/es/messages.php', '<?php return [
        "welcome" => "Bienvenido",
        "goodbye" => "Adiós",
        "greeting" => "Hola, :name!"
    ];');
    
    // JSON translation file
    file_put_contents($this->translationsPath . '/en/app.json', json_encode([
        'title' => 'My Application',
        'version' => 'Version :version'
    ]));
});

afterEach(function () {
    // Clean up test files
    if (is_dir($this->translationsPath)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->translationsPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($this->translationsPath);
    }
});

it('sets and gets locale', function () {
    expect($this->manager->getLocale())->toBe('en');
    
    $this->manager->setLocale('es');
    expect($this->manager->getLocale())->toBe('es');
});

it('translates simple keys', function () {
    $this->manager->setLocale('en');
    
    expect($this->manager->translate('messages.welcome'))->toBe('Welcome');
    expect($this->manager->translate('messages.goodbye'))->toBe('Goodbye');
});

it('translates keys in different locales', function () {
    $this->manager->setLocale('en');
    expect($this->manager->translate('messages.welcome'))->toBe('Welcome');
    
    $this->manager->setLocale('es');
    expect($this->manager->translate('messages.welcome'))->toBe('Bienvenido');
});

it('replaces placeholders in translations', function () {
    $this->manager->setLocale('en');
    
    $result = $this->manager->translate('messages.greeting', ['name' => 'John']);
    expect($result)->toBe('Hello, John!');
});

it('handles nested translation keys', function () {
    $this->manager->setLocale('en');
    
    expect($this->manager->translate('messages.nested.key'))->toBe('Nested value');
});

it('returns key when translation not found', function () {
    $this->manager->setLocale('en');
    
    expect($this->manager->translate('messages.nonexistent'))->toBe('messages.nonexistent');
});

it('falls back to fallback locale', function () {
    $this->manager->setLocale('es');
    $this->manager->setFallbackLocale('en');
    
    // 'nested.key' only exists in English
    expect($this->manager->translate('messages.nested.key'))->toBe('Nested value');
});

it('loads JSON translation files', function () {
    $this->manager->setLocale('en');
    
    expect($this->manager->translate('app.title'))->toBe('My Application');
    expect($this->manager->translate('app.version', ['version' => '1.0']))->toBe('Version 1.0');
});

it('checks if translation exists', function () {
    $this->manager->setLocale('en');
    
    expect($this->manager->has('messages.welcome'))->toBeTrue();
    expect($this->manager->has('messages.nonexistent'))->toBeFalse();
});

it('gets all translations for a locale', function () {
    $this->manager->setLocale('en');
    
    $all = $this->manager->all();
    
    expect($all)->toBeArray();
    expect($all)->toHaveKey('messages');
    expect($all['messages'])->toHaveKey('welcome');
});

it('detects locale from Accept-Language header', function () {
    $locale = $this->manager->detectLocale('en-US,en;q=0.9,es;q=0.8', ['en', 'es']);
    expect($locale)->toBe('en');
    
    $locale = $this->manager->detectLocale('es-ES,es;q=0.9', ['en', 'es']);
    expect($locale)->toBe('es');
    
    $locale = $this->manager->detectLocale('fr-FR,fr;q=0.9', ['en', 'es']);
    expect($locale)->toBe('en'); // Falls back to default
});

it('detects locale with quality values', function () {
    $locale = $this->manager->detectLocale('es;q=0.8,en;q=0.9', ['en', 'es']);
    expect($locale)->toBe('en'); // Higher quality
});

it('gets available locales', function () {
    $locales = $this->manager->getAvailableLocales();
    
    expect($locales)->toBeArray();
    expect($locales)->toContain('en');
    expect($locales)->toContain('es');
});

it('handles missing translation directory gracefully', function () {
    $manager = new TranslationManager(__DIR__ . '/nonexistent');
    
    expect($manager->translate('test.key'))->toBe('test.key');
    expect($manager->getAvailableLocales())->toBe(['en']);
});

it('translates with specific locale parameter', function () {
    $this->manager->setLocale('en');
    
    $result = $this->manager->translate('messages.welcome', [], 'es');
    expect($result)->toBe('Bienvenido');
});

it('handles multiple placeholder replacements', function () {
    file_put_contents($this->translationsPath . '/en/test.php', '<?php return [
        "message" => "Hello :name, you have :count messages"
    ];');
    
    $this->manager->setLocale('en');
    
    $result = $this->manager->translate('test.message', [
        'name' => 'Alice',
        'count' => 5
    ]);
    
    expect($result)->toBe('Hello Alice, you have 5 messages');
});
