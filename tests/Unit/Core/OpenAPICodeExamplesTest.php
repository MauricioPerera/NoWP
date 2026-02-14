<?php

use Framework\Core\OpenAPIGenerator;
use Framework\Core\Router;

beforeEach(function () {
    $this->router = new Router();
    $this->generator = new OpenAPIGenerator($this->router, [
        'base_url' => 'http://api.example.com',
    ]);
});

test('includes code examples in operation description', function () {
    $this->router->get('/api/contents', fn() => 'list');
    
    $spec = $this->generator->generate();
    
    $description = $spec['paths']['/api/contents']['get']['description'];
    
    expect($description)->toContain('Code Examples');
    expect($description)->toContain('cURL');
    expect($description)->toContain('PHP');
    expect($description)->toContain('JavaScript');
});

test('generates cURL example', function () {
    $this->router->get('/api/contents/{id}', fn() => 'show');
    
    $spec = $this->generator->generate();
    
    $description = $spec['paths']['/api/contents/{id}']['get']['description'];
    
    expect($description)->toContain('curl -X GET');
    expect($description)->toContain('Authorization: Bearer YOUR_TOKEN');
    expect($description)->toContain('http://api.example.com/api/contents/1');
});

test('generates PHP example', function () {
    $this->router->get('/api/contents', fn() => 'list');
    
    $spec = $this->generator->generate();
    
    $description = $spec['paths']['/api/contents']['get']['description'];
    
    expect($description)->toContain('$client = new GuzzleHttp\\Client()');
    expect($description)->toContain('$response = $client->request');
    expect($description)->toContain('json_decode');
});

test('generates JavaScript example', function () {
    $this->router->get('/api/contents', fn() => 'list');
    
    $spec = $this->generator->generate();
    
    $description = $spec['paths']['/api/contents']['get']['description'];
    
    expect($description)->toContain('const response = await fetch');
    expect($description)->toContain('await response.json()');
});

test('includes request body in POST examples', function () {
    $this->router->post('/api/contents', fn() => 'create');
    
    $spec = $this->generator->generate();
    
    $description = $spec['paths']['/api/contents']['post']['description'];
    
    // cURL should have -d flag with data
    expect($description)->toContain('-d');
    
    // PHP should have json key
    expect($description)->toContain("'json' =>");
    
    // JavaScript should have body
    expect($description)->toContain('body: JSON.stringify');
});

test('uses example values for path parameters', function () {
    $this->router->get('/api/contents/{id}', fn() => 'show');
    
    $spec = $this->generator->generate();
    
    $description = $spec['paths']['/api/contents/{id}']['get']['description'];
    
    // Should replace {id} with example value
    expect($description)->toContain('/api/contents/1');
    expect($description)->not->toContain('{id}');
});

test('includes appropriate example data for content endpoints', function () {
    $this->router->post('/api/contents', fn() => 'create');
    
    $spec = $this->generator->generate();
    
    $description = $spec['paths']['/api/contents']['post']['description'];
    
    expect($description)->toContain('title');
    expect($description)->toContain('content');
    expect($description)->toContain('status');
});

test('includes appropriate example data for user endpoints', function () {
    $this->router->post('/api/users', fn() => 'create');
    
    $spec = $this->generator->generate();
    
    $description = $spec['paths']['/api/users']['post']['description'];
    
    expect($description)->toContain('email');
    expect($description)->toContain('password');
    expect($description)->toContain('displayName');
});

test('uses correct language tags for code blocks', function () {
    $this->router->get('/api/test', fn() => 'test');
    
    $spec = $this->generator->generate();
    
    $description = $spec['paths']['/api/test']['get']['description'];
    
    expect($description)->toContain('```bash');  // cURL
    expect($description)->toContain('```php');   // PHP
    expect($description)->toContain('```javascript');  // JavaScript
});
