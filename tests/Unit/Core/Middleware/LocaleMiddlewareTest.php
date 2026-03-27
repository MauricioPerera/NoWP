<?php

use ChimeraNoWP\Core\Middleware\LocaleMiddleware;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Core\TranslationManager;

beforeEach(function () {
    $this->translationsPath = __DIR__ . '/../../../fixtures/translations';
    $this->translator = new TranslationManager($this->translationsPath);
    
    // Create test translation files
    if (!is_dir($this->translationsPath . '/en')) {
        mkdir($this->translationsPath . '/en', 0755, true);
    }
    if (!is_dir($this->translationsPath . '/es')) {
        mkdir($this->translationsPath . '/es', 0755, true);
    }
    
    file_put_contents($this->translationsPath . '/en/test.php', '<?php return ["key" => "value"];');
    file_put_contents($this->translationsPath . '/es/test.php', '<?php return ["key" => "valor"];');
});

afterEach(function () {
    // Clean up
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

it('uses default locale when no preference specified', function () {
    $middleware = new LocaleMiddleware($this->translator, ['en', 'es'], 'en');
    $request = new Request('GET', '/api/test');
    
    $middleware->handle($request, function ($req) {
        expect($req->getAttribute('locale'))->toBe('en');
        return new Response('OK', 200);
    });
    
    expect($this->translator->getLocale())->toBe('en');
});

it('detects locale from query parameter', function () {
    $middleware = new LocaleMiddleware($this->translator, ['en', 'es'], 'en');
    $request = new Request('GET', '/api/test', [], ['lang' => 'es']);
    
    $middleware->handle($request, function ($req) {
        expect($req->getAttribute('locale'))->toBe('es');
        return new Response('OK', 200);
    });
    
    expect($this->translator->getLocale())->toBe('es');
});

it('detects locale from X-Locale header', function () {
    $middleware = new LocaleMiddleware($this->translator, ['en', 'es'], 'en');
    $request = new Request('GET', '/api/test', ['X-Locale' => 'es']);
    
    $middleware->handle($request, function ($req) {
        expect($req->getAttribute('locale'))->toBe('es');
        return new Response('OK', 200);
    });
    
    expect($this->translator->getLocale())->toBe('es');
});

it('detects locale from Accept-Language header', function () {
    $middleware = new LocaleMiddleware($this->translator, ['en', 'es'], 'en');
    $request = new Request('GET', '/api/test', ['Accept-Language' => 'es-ES,es;q=0.9']);
    
    $middleware->handle($request, function ($req) {
        expect($req->getAttribute('locale'))->toBe('es');
        return new Response('OK', 200);
    });
    
    expect($this->translator->getLocale())->toBe('es');
});

it('prioritizes query parameter over headers', function () {
    $middleware = new LocaleMiddleware($this->translator, ['en', 'es'], 'en');
    $request = new Request('GET', '/api/test', [
        'X-Locale' => 'en',
        'Accept-Language' => 'en-US'
    ], ['lang' => 'es']);
    
    $middleware->handle($request, function ($req) {
        expect($req->getAttribute('locale'))->toBe('es');
        return new Response('OK', 200);
    });
});

it('prioritizes X-Locale header over Accept-Language', function () {
    $middleware = new LocaleMiddleware($this->translator, ['en', 'es'], 'en');
    $request = new Request('GET', '/api/test', [
        'X-Locale' => 'es',
        'Accept-Language' => 'en-US'
    ]);
    
    $middleware->handle($request, function ($req) {
        expect($req->getAttribute('locale'))->toBe('es');
        return new Response('OK', 200);
    });
});

it('ignores unsupported locales', function () {
    $middleware = new LocaleMiddleware($this->translator, ['en', 'es'], 'en');
    $request = new Request('GET', '/api/test', [], ['lang' => 'fr']);
    
    $middleware->handle($request, function ($req) {
        expect($req->getAttribute('locale'))->toBe('en'); // Falls back to default
        return new Response('OK', 200);
    });
});

it('supports locale query parameter', function () {
    $middleware = new LocaleMiddleware($this->translator, ['en', 'es'], 'en');
    $request = new Request('GET', '/api/test', [], ['locale' => 'es']);
    
    $middleware->handle($request, function ($req) {
        expect($req->getAttribute('locale'))->toBe('es');
        return new Response('OK', 200);
    });
});

it('stores locale in request attributes', function () {
    $middleware = new LocaleMiddleware($this->translator, ['en', 'es'], 'en');
    $request = new Request('GET', '/api/test', [], ['lang' => 'es']);
    
    $response = $middleware->handle($request, function ($req) {
        expect($req->hasAttribute('locale'))->toBeTrue();
        expect($req->getAttribute('locale'))->toBe('es');
        return new Response('OK', 200);
    });
    
    expect($response->getStatusCode())->toBe(200);
});

it('handles complex Accept-Language headers', function () {
    $middleware = new LocaleMiddleware($this->translator, ['en', 'es', 'fr'], 'en');
    $request = new Request('GET', '/api/test', [
        'Accept-Language' => 'fr-FR;q=0.7,es-ES;q=0.9,en-US;q=0.8'
    ]);
    
    $middleware->handle($request, function ($req) {
        expect($req->getAttribute('locale'))->toBe('es'); // Highest quality
        return new Response('OK', 200);
    });
});
