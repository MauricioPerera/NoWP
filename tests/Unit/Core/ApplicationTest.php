<?php

use ChimeraNoWP\Core\Application;
use ChimeraNoWP\Core\Container;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Core\Router;

describe('Application', function () {
    it('creates application with config', function () {
        $config = ['app' => ['name' => 'Test App']];
        $app = new Application($config);
        
        expect($app)->toBeInstanceOf(Application::class)
            ->and($app->config('app.name'))->toBe('Test App');
    });

    it('boots application successfully', function () {
        $app = new Application();
        
        expect($app->isBooted())->toBeFalse();
        
        $app->boot();
        
        expect($app->isBooted())->toBeTrue();
    });

    it('boots only once', function () {
        $app = new Application();
        
        $app->boot();
        $app->boot(); // Second boot should be ignored
        
        expect($app->isBooted())->toBeTrue();
    });

    it('provides access to container', function () {
        $app = new Application();
        $container = $app->getContainer();
        
        expect($container)->toBeInstanceOf(Container::class);
    });

    it('registers container and application in container', function () {
        $app = new Application();
        $container = $app->getContainer();
        
        expect($container->has(Container::class))->toBeTrue()
            ->and($container->has(Application::class))->toBeTrue()
            ->and($container->resolve(Application::class))->toBe($app);
    });

    it('loads configuration from config files', function () {
        $app = new Application();
        $app->boot();
        
        // Should load app.php config
        expect($app->config('app.name'))->not->toBeNull();
    });

    it('registers router as singleton', function () {
        $app = new Application();
        $app->boot();
        
        $container = $app->getContainer();
        $router1 = $container->resolve(Router::class);
        $router2 = $container->resolve(Router::class);
        
        expect($router1)->toBe($router2);
    });

    it('handles 404 for non-existent routes', function () {
        $app = new Application();
        $app->boot();
        
        $request = new Request('GET', '/non-existent');
        $response = $app->handle($request);
        
        expect($response->getStatusCode())->toBe(404)
            ->and($response->getContent())->toContain('ROUTE_NOT_FOUND');
    });

    it('returns config value with dot notation', function () {
        $config = [
            'app' => [
                'name' => 'Test',
                'nested' => [
                    'value' => 'deep'
                ]
            ]
        ];
        $app = new Application($config);
        
        expect($app->config('app.name'))->toBe('Test')
            ->and($app->config('app.nested.value'))->toBe('deep')
            ->and($app->config('app.missing', 'default'))->toBe('default');
    });

    it('creates error response in debug mode', function () {
        $config = ['app' => ['debug' => true]];
        $app = new Application($config);
        $app->boot();
        
        // Register a route that throws an exception
        $router = $app->getContainer()->resolve(Router::class);
        $router->get('/error', function () {
            throw new \Exception('Test error');
        });
        
        $request = new Request('GET', '/error');
        $response = $app->handle($request);
        
        expect($response->getStatusCode())->toBe(500)
            ->and($response->getContent())->toContain('Test error')
            ->and($response->getContent())->toContain('trace');
    });

    it('hides error details when debug is off', function () {
        $config = ['app' => ['debug' => false]];
        $app = new Application($config);
        $app->boot();
        
        // Register a route that throws an exception
        $router = $app->getContainer()->resolve(Router::class);
        $router->get('/error', function () {
            throw new \Exception('Sensitive error');
        });
        
        $request = new Request('GET', '/error');
        $response = $app->handle($request);
        
        $content = $response->getContent();
        
        expect($response->getStatusCode())->toBe(500)
            ->and($content)->not->toContain('Sensitive error')
            ->and($content)->toContain('An unexpected error occurred');
    });

    it('auto-boots when handling request if not booted', function () {
        $app = new Application();
        
        expect($app->isBooted())->toBeFalse();
        
        $request = new Request('GET', '/test');
        $app->handle($request);
        
        expect($app->isBooted())->toBeTrue();
    });
});
