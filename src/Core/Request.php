<?php

namespace Framework\Core;

/**
 * HTTP Request Class
 * 
 * Encapsulates HTTP request data including method, URI, headers, and body.
 */
class Request
{
    /**
     * Request method (GET, POST, PUT, DELETE, etc.)
     */
    private string $method;

    /**
     * Request URI
     */
    private string $uri;

    /**
     * Request headers
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Query parameters
     * @var array<string, mixed>
     */
    private array $query;

    /**
     * Request body data
     * @var array<string, mixed>
     */
    private array $body;

    /**
     * Server variables
     * @var array<string, mixed>
     */
    private array $server;

    /**
     * Uploaded files
     * @var array<string, mixed>
     */
    private array $files;

    /**
     * Request attributes (for middleware data injection)
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Create a new Request instance
     * 
     * @param string $method
     * @param string $uri
     * @param array $headers
     * @param array $query
     * @param array $body
     * @param array $server
     * @param array $files
     */
    public function __construct(
        string $method,
        string $uri,
        array $headers = [],
        array $query = [],
        array $body = [],
        array $server = [],
        array $files = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->headers = $headers;
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
        $this->files = $files;
    }

    /**
     * Create a Request from PHP globals
     * 
     * @return self
     */
    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Parse URI to remove query string
        $uriParts = parse_url($uri);
        $path = $uriParts['path'] ?? '/';
        
        // Get headers
        $headers = self::getHeadersFromServer($_SERVER);
        
        // Get request body for POST/PUT/PATCH
        $body = [];
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $contentType = $headers['Content-Type'] ?? '';
            
            if (str_contains($contentType, 'application/json')) {
                $json = file_get_contents('php://input');
                $body = json_decode($json, true) ?? [];
            } else {
                $body = $_POST;
            }
        }
        
        return new self(
            $method,
            $path,
            $headers,
            $_GET,
            $body,
            $_SERVER,
            $_FILES
        );
    }

    /**
     * Extract headers from server variables
     * 
     * @param array $server
     * @return array<string, string>
     */
    private static function getHeadersFromServer(array $server): array
    {
        $headers = [];
        
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $name = ucwords(strtolower($name), '-');
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = str_replace('_', '-', $key);
                $name = ucwords(strtolower($name), '-');
                $headers[$name] = $value;
            }
        }
        
        return $headers;
    }

    /**
     * Get request method
     * 
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get request URI
     * 
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get request path (alias for getUri)
     * 
     * @return string
     */
    public function getPath(): string
    {
        return $this->uri;
    }

    /**
     * Get all headers
     * 
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a specific header
     * 
     * @param string $name
     * @param string|null $default
     * @return string|null
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Check if header exists
     * 
     * @param string $name
     * @return bool
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * Get all query parameters
     * 
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * Get a query parameter
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get all body data
     * 
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * Get a body parameter
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Get the raw request body as string.
     */
    public function rawBody(): string
    {
        return file_get_contents('php://input') ?: json_encode($this->body);
    }

    /**
     * Get body as parsed JSON array.
     */
    public function json(): array
    {
        return $this->body;
    }

    /**
     * Get all input (query + body)
     * 
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * Get server variables
     * 
     * @return array<string, mixed>
     */
    public function getServer(): array
    {
        return $this->server;
    }

    /**
     * Get uploaded files
     * 
     * @return array<string, mixed>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Check if request is JSON
     * 
     * @return bool
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type', '');
        return str_contains($contentType, 'application/json');
    }

    /**
     * Check if request expects JSON response
     * 
     * @return bool
     */
    public function expectsJson(): bool
    {
        $accept = $this->getHeader('Accept', '');
        return str_contains($accept, 'application/json');
    }

    /**
     * Set a request attribute
     * 
     * Attributes are used by middleware to store data in the request
     * (e.g., authenticated user data)
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get a request attribute
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Check if attribute exists
     * 
     * @param string $key
     * @return bool
     */
    public function hasAttribute(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Get all attributes
     * 
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get authenticated user data (convenience method)
     * 
     * @return array|null
     */
    public function user(): ?array
    {
        return $this->getAttribute('user');
    }
}
