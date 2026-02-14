<?php

use Framework\Core\DocsController;
use Framework\Core\OpenAPIGenerator;
use Framework\Core\Router;

beforeEach(function () {
    $router = new Router();
    $router->get('/api/test', fn() => 'test');
    
    $generator = new OpenAPIGenerator($router);
    $this->controller = new DocsController($generator);
});

test('index returns HTML response', function () {
    $response = $this->controller->index();
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getHeader('Content-Type'))->toContain('text/html');
});

test('index contains Swagger UI', function () {
    $response = $this->controller->index();
    $content = $response->getContent();
    
    expect($content)->toContain('swagger-ui');
    expect($content)->toContain('SwaggerUIBundle');
    expect($content)->toContain('/api/docs/spec');
});

test('spec returns JSON response', function () {
    $response = $this->controller->spec();
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getHeader('Content-Type'))->toContain('application/json');
});

test('spec returns valid OpenAPI specification', function () {
    $response = $this->controller->spec();
    $content = $response->getContent();
    
    $spec = json_decode($content, true);
    
    expect($spec)->toBeArray();
    expect($spec)->toHaveKey('openapi');
    expect($spec)->toHaveKey('info');
    expect($spec)->toHaveKey('paths');
});

test('spec includes registered routes', function () {
    $response = $this->controller->spec();
    $content = $response->getContent();
    
    $spec = json_decode($content, true);
    
    expect($spec['paths'])->toHaveKey('/api/test');
});
