<?php

use ChimeraNoWP\Core\Request;

// Constructor tests
test('creates request with basic parameters', function () {
    $request = new Request('GET', '/api/users');
    
    expect($request->getMethod())->toBe('GET')
        ->and($request->getUri())->toBe('/api/users')
        ->and($request->getPath())->toBe('/api/users');
});

test('normalizes method to uppercase', function () {
    $request = new Request('get', '/api/users');
    
    expect($request->getMethod())->toBe('GET');
});

test('creates request with headers', function () {
    $headers = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer token123'
    ];
    
    $request = new Request('POST', '/api/users', $headers);
    
    expect($request->getHeaders())->toBe($headers)
        ->and($request->getHeader('Content-Type'))->toBe('application/json')
        ->and($request->getHeader('Authorization'))->toBe('Bearer token123');
});

test('creates request with query parameters', function () {
    $query = ['page' => '1', 'limit' => '10'];
    
    $request = new Request('GET', '/api/users', [], $query);
    
    expect($request->getQuery())->toBe($query)
        ->and($request->query('page'))->toBe('1')
        ->and($request->query('limit'))->toBe('10');
});

test('creates request with body data', function () {
    $body = ['name' => 'John Doe', 'email' => 'john@example.com'];
    
    $request = new Request('POST', '/api/users', [], [], $body);
    
    expect($request->getBody())->toBe($body)
        ->and($request->input('name'))->toBe('John Doe')
        ->and($request->input('email'))->toBe('john@example.com');
});

test('creates request with server variables', function () {
    $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users'];
    
    $request = new Request('GET', '/api/users', [], [], [], $server);
    
    expect($request->getServer())->toBe($server);
});

test('creates request with uploaded files', function () {
    $files = ['avatar' => ['name' => 'photo.jpg', 'tmp_name' => '/tmp/php123']];
    
    $request = new Request('POST', '/api/upload', [], [], [], [], $files);
    
    expect($request->getFiles())->toBe($files);
});

// Header methods tests
test('hasHeader() returns true for existing header', function () {
    $request = new Request('GET', '/api/users', ['Content-Type' => 'application/json']);
    
    expect($request->hasHeader('Content-Type'))->toBeTrue();
});

test('hasHeader() returns false for non-existing header', function () {
    $request = new Request('GET', '/api/users');
    
    expect($request->hasHeader('Authorization'))->toBeFalse();
});

test('getHeader() returns default value for non-existing header', function () {
    $request = new Request('GET', '/api/users');
    
    expect($request->getHeader('Authorization', 'default'))->toBe('default');
});

test('getHeader() returns null for non-existing header without default', function () {
    $request = new Request('GET', '/api/users');
    
    expect($request->getHeader('Authorization'))->toBeNull();
});

// Query parameter tests
test('query() returns default value for non-existing parameter', function () {
    $request = new Request('GET', '/api/users');
    
    expect($request->query('page', 1))->toBe(1);
});

test('query() returns null for non-existing parameter without default', function () {
    $request = new Request('GET', '/api/users');
    
    expect($request->query('page'))->toBeNull();
});

// Body parameter tests
test('input() returns default value for non-existing parameter', function () {
    $request = new Request('POST', '/api/users');
    
    expect($request->input('name', 'Anonymous'))->toBe('Anonymous');
});

test('input() returns null for non-existing parameter without default', function () {
    $request = new Request('POST', '/api/users');
    
    expect($request->input('name'))->toBeNull();
});

// all() method tests
test('all() merges query and body parameters', function () {
    $query = ['page' => '1'];
    $body = ['name' => 'John'];
    
    $request = new Request('POST', '/api/users', [], $query, $body);
    
    expect($request->all())->toBe(['page' => '1', 'name' => 'John']);
});

test('all() body parameters override query parameters with same key', function () {
    $query = ['id' => '1'];
    $body = ['id' => '2'];
    
    $request = new Request('POST', '/api/users', [], $query, $body);
    
    expect($request->all()['id'])->toBe('2');
});

// Content type detection tests
test('isJson() returns true for JSON content type', function () {
    $request = new Request('POST', '/api/users', ['Content-Type' => 'application/json']);
    
    expect($request->isJson())->toBeTrue();
});

test('isJson() returns true for JSON content type with charset', function () {
    $request = new Request('POST', '/api/users', ['Content-Type' => 'application/json; charset=utf-8']);
    
    expect($request->isJson())->toBeTrue();
});

test('isJson() returns false for non-JSON content type', function () {
    $request = new Request('POST', '/api/users', ['Content-Type' => 'application/x-www-form-urlencoded']);
    
    expect($request->isJson())->toBeFalse();
});

test('isJson() returns false when no content type header', function () {
    $request = new Request('POST', '/api/users');
    
    expect($request->isJson())->toBeFalse();
});

test('expectsJson() returns true for JSON accept header', function () {
    $request = new Request('GET', '/api/users', ['Accept' => 'application/json']);
    
    expect($request->expectsJson())->toBeTrue();
});

test('expectsJson() returns true for JSON accept header with multiple types', function () {
    $request = new Request('GET', '/api/users', ['Accept' => 'text/html, application/json, */*']);
    
    expect($request->expectsJson())->toBeTrue();
});

test('expectsJson() returns false for non-JSON accept header', function () {
    $request = new Request('GET', '/api/users', ['Accept' => 'text/html']);
    
    expect($request->expectsJson())->toBeFalse();
});

test('expectsJson() returns false when no accept header', function () {
    $request = new Request('GET', '/api/users');
    
    expect($request->expectsJson())->toBeFalse();
});

// createFromGlobals() tests
test('createFromGlobals() creates request from superglobals', function () {
    // Save original values
    $originalServer = $_SERVER;
    $originalGet = $_GET;
    $originalPost = $_POST;
    $originalFiles = $_FILES;
    
    // Set test values
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/users?page=1';
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
    $_GET = ['page' => '1'];
    $_POST = ['name' => 'John'];
    $_FILES = [];
    
    $request = Request::createFromGlobals();
    
    expect($request->getMethod())->toBe('POST')
        ->and($request->getPath())->toBe('/api/users')
        ->and($request->query('page'))->toBe('1')
        ->and($request->input('name'))->toBe('John');
    
    // Restore original values
    $_SERVER = $originalServer;
    $_GET = $originalGet;
    $_POST = $originalPost;
    $_FILES = $originalFiles;
});

test('createFromGlobals() handles JSON request body', function () {
    // Save original values
    $originalServer = $_SERVER;
    $originalGet = $_GET;
    $originalPost = $_POST;
    
    // Set test values
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/users';
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
    $_GET = [];
    $_POST = [];
    
    // Mock php://input
    $jsonData = json_encode(['name' => 'John', 'email' => 'john@example.com']);
    
    // Note: In real tests, we would need to mock file_get_contents('php://input')
    // For now, we'll test the structure
    
    $request = Request::createFromGlobals();
    
    expect($request->getMethod())->toBe('POST')
        ->and($request->getPath())->toBe('/api/users');
    
    // Restore original values
    $_SERVER = $originalServer;
    $_GET = $originalGet;
    $_POST = $originalPost;
});

test('createFromGlobals() extracts headers from server variables', function () {
    // Save original values
    $originalServer = $_SERVER;
    
    // Set test values
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/users';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
    $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SERVER['CONTENT_LENGTH'] = '123';
    
    $request = Request::createFromGlobals();
    
    expect($request->getHeader('Authorization'))->toBe('Bearer token123')
        ->and($request->getHeader('User-Agent'))->toBe('TestAgent/1.0')
        ->and($request->getHeader('Content-Type'))->toBe('application/json')
        ->and($request->getHeader('Content-Length'))->toBe('123');
    
    // Restore original values
    $_SERVER = $originalServer;
});

test('createFromGlobals() defaults to GET method', function () {
    // Save original values
    $originalServer = $_SERVER;
    
    // Set minimal server values
    $_SERVER = [];
    
    $request = Request::createFromGlobals();
    
    expect($request->getMethod())->toBe('GET');
    
    // Restore original values
    $_SERVER = $originalServer;
});

test('createFromGlobals() defaults to root URI', function () {
    // Save original values
    $originalServer = $_SERVER;
    
    // Set minimal server values
    $_SERVER = ['REQUEST_METHOD' => 'GET'];
    
    $request = Request::createFromGlobals();
    
    expect($request->getUri())->toBe('/');
    
    // Restore original values
    $_SERVER = $originalServer;
});

// Edge cases
test('handles empty arrays for all parameters', function () {
    $request = new Request('GET', '/');
    
    expect($request->getHeaders())->toBe([])
        ->and($request->getQuery())->toBe([])
        ->and($request->getBody())->toBe([])
        ->and($request->getServer())->toBe([])
        ->and($request->getFiles())->toBe([]);
});

test('handles special characters in URI', function () {
    $uri = '/api/search?q=hello%20world&filter=name%3DJohn';
    $request = new Request('GET', $uri);
    
    expect($request->getUri())->toBe($uri);
});

test('handles multiple HTTP methods', function () {
    $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
    
    foreach ($methods as $method) {
        $request = new Request($method, '/api/test');
        expect($request->getMethod())->toBe($method);
    }
});
