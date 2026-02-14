<?php

use Framework\Core\OpenAPIGenerator;
use Framework\Core\Router;

beforeEach(function () {
    $this->router = new Router();
    $this->generator = new OpenAPIGenerator($this->router, [
        'title' => 'Test API',
        'version' => '1.0.0',
        'base_url' => 'http://test.local',
    ]);
});

test('generates basic OpenAPI structure', function () {
    $spec = $this->generator->generate();
    
    expect($spec)->toHaveKeys(['openapi', 'info', 'servers', 'paths', 'components']);
    expect($spec['openapi'])->toBe('3.0.0');
    expect($spec['info']['title'])->toBe('Test API');
    expect($spec['info']['version'])->toBe('1.0.0');
});

test('includes server configuration', function () {
    $spec = $this->generator->generate();
    
    expect($spec['servers'])->toHaveCount(1);
    expect($spec['servers'][0]['url'])->toBe('http://test.local');
});

test('includes security schemes', function () {
    $spec = $this->generator->generate();
    
    expect($spec['components']['securitySchemes'])->toHaveKey('bearerAuth');
    expect($spec['components']['securitySchemes']['bearerAuth']['type'])->toBe('http');
    expect($spec['components']['securitySchemes']['bearerAuth']['scheme'])->toBe('bearer');
});

test('generates paths from routes', function () {
    $this->router->get('/api/contents', fn() => 'list');
    $this->router->get('/api/contents/{id}', fn() => 'show');
    $this->router->post('/api/contents', fn() => 'create');
    
    $spec = $this->generator->generate();
    
    expect($spec['paths'])->toHaveKey('/api/contents');
    expect($spec['paths'])->toHaveKey('/api/contents/{id}');
});

test('generates correct HTTP methods', function () {
    $this->router->get('/api/test', fn() => 'get');
    $this->router->post('/api/test', fn() => 'post');
    $this->router->put('/api/test/{id}', fn() => 'put');
    $this->router->delete('/api/test/{id}', fn() => 'delete');
    
    $spec = $this->generator->generate();
    
    expect($spec['paths']['/api/test'])->toHaveKeys(['get', 'post']);
    expect($spec['paths']['/api/test/{id}'])->toHaveKeys(['put', 'delete']);
});

test('generates path parameters', function () {
    $this->router->get('/api/contents/{id}', fn() => 'show');
    
    $spec = $this->generator->generate();
    
    $params = $spec['paths']['/api/contents/{id}']['get']['parameters'];
    expect($params)->toHaveCount(1);
    expect($params[0]['name'])->toBe('id');
    expect($params[0]['in'])->toBe('path');
    expect($params[0]['required'])->toBeTrue();
});

test('generates query parameters for list endpoints', function () {
    $this->router->get('/api/contents', fn() => 'list');
    
    $spec = $this->generator->generate();
    
    $params = $spec['paths']['/api/contents']['get']['parameters'];
    $paramNames = array_column($params, 'name');
    
    expect($paramNames)->toContain('page');
    expect($paramNames)->toContain('limit');
});

test('generates request body for POST requests', function () {
    $this->router->post('/api/contents', fn() => 'create');
    
    $spec = $this->generator->generate();
    
    expect($spec['paths']['/api/contents']['post'])->toHaveKey('requestBody');
    expect($spec['paths']['/api/contents']['post']['requestBody']['required'])->toBeTrue();
});

test('generates appropriate responses', function () {
    $this->router->get('/api/contents', fn() => 'list');
    $this->router->post('/api/contents', fn() => 'create');
    $this->router->delete('/api/contents/{id}', fn() => 'delete');
    
    $spec = $this->generator->generate();
    
    // GET should have 200
    expect($spec['paths']['/api/contents']['get']['responses'])->toHaveKey('200');
    
    // POST should have 201
    expect($spec['paths']['/api/contents']['post']['responses'])->toHaveKey('201');
    
    // DELETE should have 204
    expect($spec['paths']['/api/contents/{id}']['delete']['responses'])->toHaveKey('204');
});

test('generates tags from path', function () {
    $this->router->get('/api/contents', fn() => 'list');
    $this->router->get('/api/users', fn() => 'list');
    
    $spec = $this->generator->generate();
    
    expect($spec['paths']['/api/contents']['get']['tags'])->toContain('Contents');
    expect($spec['paths']['/api/users']['get']['tags'])->toContain('Users');
});

test('includes common schemas', function () {
    $spec = $this->generator->generate();
    
    expect($spec['components']['schemas'])->toHaveKeys(['Error', 'Content', 'User', 'Media']);
});

test('generates valid JSON', function () {
    $this->router->get('/api/test', fn() => 'test');
    
    $json = $this->generator->toJson();
    
    expect($json)->toBeString();
    
    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray();
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
});

test('can save to file', function () {
    $this->router->get('/api/test', fn() => 'test');
    
    $tempFile = sys_get_temp_dir() . '/openapi-test.json';
    
    $result = $this->generator->saveToFile($tempFile);
    
    expect($result)->toBeTrue();
    expect(file_exists($tempFile))->toBeTrue();
    
    // Clean up
    unlink($tempFile);
});
