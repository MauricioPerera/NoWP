<?php

use ChimeraNoWP\Core\Response;

// Constructor tests
test('creates response with default values', function () {
    $response = new Response();
    
    expect($response->getContent())->toBe('')
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getHeaders())->toBe([]);
});

test('creates response with content', function () {
    $response = new Response('Hello World');
    
    expect($response->getContent())->toBe('Hello World');
});

test('creates response with status code', function () {
    $response = new Response('', 404);
    
    expect($response->getStatusCode())->toBe(404);
});

test('creates response with headers', function () {
    $headers = ['Content-Type' => 'text/html', 'X-Custom' => 'value'];
    $response = new Response('', 200, $headers);
    
    expect($response->getHeaders())->toBe($headers);
});

test('creates response with all parameters', function () {
    $response = new Response('Test content', 201, ['X-Test' => 'value']);
    
    expect($response->getContent())->toBe('Test content')
        ->and($response->getStatusCode())->toBe(201)
        ->and($response->getHeader('X-Test'))->toBe('value');
});

// JSON response tests
test('json() creates JSON response with default status', function () {
    $data = ['name' => 'John', 'age' => 30];
    $response = Response::json($data);
    
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeader('Content-Type'))->toBe('application/json')
        ->and($response->getContent())->toBe(json_encode($data));
});

test('json() creates JSON response with custom status', function () {
    $data = ['id' => 1];
    $response = Response::json($data, 201);
    
    expect($response->getStatusCode())->toBe(201);
});

test('json() creates JSON response with additional headers', function () {
    $data = ['test' => 'value'];
    $response = Response::json($data, 200, ['X-Custom' => 'header']);
    
    expect($response->getHeader('Content-Type'))->toBe('application/json')
        ->and($response->getHeader('X-Custom'))->toBe('header');
});

test('json() handles arrays', function () {
    $data = [1, 2, 3, 4, 5];
    $response = Response::json($data);
    
    expect($response->getContent())->toBe(json_encode($data));
});

test('json() handles objects', function () {
    $data = (object)['name' => 'John', 'email' => 'john@example.com'];
    $response = Response::json($data);
    
    expect($response->getContent())->toBe(json_encode($data));
});

test('json() handles null', function () {
    $response = Response::json(null);
    
    expect($response->getContent())->toBe('null');
});

test('json() handles boolean values', function () {
    $response = Response::json(true);
    
    expect($response->getContent())->toBe('true');
});

test('json() handles nested structures', function () {
    $data = [
        'user' => [
            'name' => 'John',
            'address' => [
                'city' => 'New York',
                'country' => 'USA'
            ]
        ]
    ];
    $response = Response::json($data);
    
    expect($response->getContent())->toBe(json_encode($data));
});

// Success response tests
test('success() creates success response with default values', function () {
    $response = Response::success();
    
    $decoded = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(200)
        ->and($decoded['success'])->toBeTrue()
        ->and($decoded['message'])->toBe('Success');
});

test('success() creates success response with data', function () {
    $data = ['id' => 1, 'name' => 'John'];
    $response = Response::success($data);
    
    $decoded = json_decode($response->getContent(), true);
    
    expect($decoded['success'])->toBeTrue()
        ->and($decoded['data'])->toBe($data);
});

test('success() creates success response with custom message', function () {
    $response = Response::success(null, 'Operation completed');
    
    $decoded = json_decode($response->getContent(), true);
    
    expect($decoded['message'])->toBe('Operation completed');
});

test('success() creates success response with custom status code', function () {
    $response = Response::success(['id' => 1], 'Created', 201);
    
    expect($response->getStatusCode())->toBe(201);
});

test('success() omits data key when data is null', function () {
    $response = Response::success(null, 'Success');
    
    $decoded = json_decode($response->getContent(), true);
    
    expect($decoded)->toHaveKey('success')
        ->and($decoded)->toHaveKey('message')
        ->and($decoded)->not->toHaveKey('data');
});

test('success() includes data key when data is provided', function () {
    $response = Response::success(['test' => 'value']);
    
    $decoded = json_decode($response->getContent(), true);
    
    expect($decoded)->toHaveKey('data');
});

// Error response tests
test('error() creates error response with default values', function () {
    $response = Response::error('Something went wrong');
    
    $decoded = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(400)
        ->and($decoded['error']['code'])->toBe('ERROR')
        ->and($decoded['error']['message'])->toBe('Something went wrong');
});

test('error() creates error response with custom code', function () {
    $response = Response::error('Not found', 'NOT_FOUND');
    
    $decoded = json_decode($response->getContent(), true);
    
    expect($decoded['error']['code'])->toBe('NOT_FOUND');
});

test('error() creates error response with custom status code', function () {
    $response = Response::error('Not found', 'NOT_FOUND', 404);
    
    expect($response->getStatusCode())->toBe(404);
});

test('error() creates error response with details', function () {
    $details = ['field' => 'email', 'reason' => 'Invalid format'];
    $response = Response::error('Validation failed', 'VALIDATION_ERROR', 422, $details);
    
    $decoded = json_decode($response->getContent(), true);
    
    expect($decoded['error']['details'])->toBe($details);
});

test('error() omits details when empty', function () {
    $response = Response::error('Error occurred');
    
    $decoded = json_decode($response->getContent(), true);
    
    expect($decoded['error'])->not->toHaveKey('details');
});

test('error() includes details when provided', function () {
    $response = Response::error('Error', 'ERROR', 400, ['info' => 'test']);
    
    $decoded = json_decode($response->getContent(), true);
    
    expect($decoded['error'])->toHaveKey('details');
});

// Setter tests
test('setContent() updates content', function () {
    $response = new Response('Original');
    $response->setContent('Updated');
    
    expect($response->getContent())->toBe('Updated');
});

test('setContent() returns self for chaining', function () {
    $response = new Response();
    $result = $response->setContent('Test');
    
    expect($result)->toBe($response);
});

test('setStatusCode() updates status code', function () {
    $response = new Response();
    $response->setStatusCode(404);
    
    expect($response->getStatusCode())->toBe(404);
});

test('setStatusCode() returns self for chaining', function () {
    $response = new Response();
    $result = $response->setStatusCode(201);
    
    expect($result)->toBe($response);
});

test('setHeader() adds new header', function () {
    $response = new Response();
    $response->setHeader('X-Custom', 'value');
    
    expect($response->getHeader('X-Custom'))->toBe('value');
});

test('setHeader() updates existing header', function () {
    $response = new Response('', 200, ['X-Test' => 'old']);
    $response->setHeader('X-Test', 'new');
    
    expect($response->getHeader('X-Test'))->toBe('new');
});

test('setHeader() returns self for chaining', function () {
    $response = new Response();
    $result = $response->setHeader('X-Test', 'value');
    
    expect($result)->toBe($response);
});

test('setHeaders() adds multiple headers', function () {
    $response = new Response();
    $headers = ['X-First' => 'value1', 'X-Second' => 'value2'];
    $response->setHeaders($headers);
    
    expect($response->getHeader('X-First'))->toBe('value1')
        ->and($response->getHeader('X-Second'))->toBe('value2');
});

test('setHeaders() returns self for chaining', function () {
    $response = new Response();
    $result = $response->setHeaders(['X-Test' => 'value']);
    
    expect($result)->toBe($response);
});

test('method chaining works correctly', function () {
    $response = (new Response())
        ->setContent('Test')
        ->setStatusCode(201)
        ->setHeader('X-Custom', 'value');
    
    expect($response->getContent())->toBe('Test')
        ->and($response->getStatusCode())->toBe(201)
        ->and($response->getHeader('X-Custom'))->toBe('value');
});

// Getter tests
test('getHeader() returns null for non-existing header', function () {
    $response = new Response();
    
    expect($response->getHeader('X-NonExistent'))->toBeNull();
});

test('getHeader() returns existing header value', function () {
    $response = new Response('', 200, ['X-Test' => 'value']);
    
    expect($response->getHeader('X-Test'))->toBe('value');
});

// Status check tests
test('isSuccessful() returns true for 2xx status codes', function () {
    $codes = [200, 201, 202, 204, 206];
    
    foreach ($codes as $code) {
        $response = new Response('', $code);
        expect($response->isSuccessful())->toBeTrue();
    }
});

test('isSuccessful() returns false for non-2xx status codes', function () {
    $codes = [199, 300, 400, 404, 500];
    
    foreach ($codes as $code) {
        $response = new Response('', $code);
        expect($response->isSuccessful())->toBeFalse();
    }
});

test('isClientError() returns true for 4xx status codes', function () {
    $codes = [400, 401, 403, 404, 422, 429];
    
    foreach ($codes as $code) {
        $response = new Response('', $code);
        expect($response->isClientError())->toBeTrue();
    }
});

test('isClientError() returns false for non-4xx status codes', function () {
    $codes = [200, 201, 300, 399, 500];
    
    foreach ($codes as $code) {
        $response = new Response('', $code);
        expect($response->isClientError())->toBeFalse();
    }
});

test('isServerError() returns true for 5xx status codes', function () {
    $codes = [500, 501, 502, 503, 504];
    
    foreach ($codes as $code) {
        $response = new Response('', $code);
        expect($response->isServerError())->toBeTrue();
    }
});

test('isServerError() returns false for non-5xx status codes', function () {
    $codes = [200, 300, 400, 404, 499];
    
    foreach ($codes as $code) {
        $response = new Response('', $code);
        expect($response->isServerError())->toBeFalse();
    }
});

// Status text tests
test('getStatusText() returns correct text for known status codes', function () {
    $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];
    
    foreach ($statusTexts as $code => $text) {
        $response = new Response('', $code);
        expect($response->getStatusText())->toBe($text);
    }
});

test('getStatusText() returns Unknown Status for unknown status codes', function () {
    $response = new Response('', 999);
    
    expect($response->getStatusText())->toBe('Unknown Status');
});

// Edge cases
test('handles empty content', function () {
    $response = new Response('');
    
    expect($response->getContent())->toBe('');
});

test('handles large content', function () {
    $largeContent = str_repeat('A', 10000);
    $response = new Response($largeContent);
    
    expect($response->getContent())->toBe($largeContent)
        ->and(strlen($response->getContent()))->toBe(10000);
});

test('handles special characters in content', function () {
    $content = "Special chars: <>&\"'\n\t";
    $response = new Response($content);
    
    expect($response->getContent())->toBe($content);
});

test('handles unicode in JSON response', function () {
    $data = ['message' => 'Hello 世界 🌍'];
    $response = Response::json($data);
    
    $decoded = json_decode($response->getContent(), true);
    expect($decoded['message'])->toBe('Hello 世界 🌍');
});

test('handles empty array in JSON response', function () {
    $response = Response::json([]);
    
    expect($response->getContent())->toBe('[]');
});

test('handles empty object in JSON response', function () {
    $response = Response::json((object)[]);
    
    expect($response->getContent())->toBe('{}');
});

// Common HTTP status codes
test('handles common success status codes', function () {
    $response200 = new Response('', 200);
    $response201 = new Response('', 201);
    $response204 = new Response('', 204);
    
    expect($response200->isSuccessful())->toBeTrue()
        ->and($response201->isSuccessful())->toBeTrue()
        ->and($response204->isSuccessful())->toBeTrue();
});

test('handles common client error status codes', function () {
    $response400 = new Response('', 400);
    $response401 = new Response('', 401);
    $response403 = new Response('', 403);
    $response404 = new Response('', 404);
    
    expect($response400->isClientError())->toBeTrue()
        ->and($response401->isClientError())->toBeTrue()
        ->and($response403->isClientError())->toBeTrue()
        ->and($response404->isClientError())->toBeTrue();
});

test('handles common server error status codes', function () {
    $response500 = new Response('', 500);
    $response503 = new Response('', 503);
    
    expect($response500->isServerError())->toBeTrue()
        ->and($response503->isServerError())->toBeTrue();
});
