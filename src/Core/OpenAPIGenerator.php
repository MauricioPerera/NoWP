<?php

/**
 * OpenAPI Generator
 * 
 * Generates OpenAPI 3.0 specification from routes and controllers.
 * 
 * Requirements: 13.1, 13.4
 */

declare(strict_types=1);

namespace ChimeraNoWP\Core;

class OpenAPIGenerator
{
    private Router $router;
    private array $config;
    
    public function __construct(Router $router, array $config = [])
    {
        $this->router = $router;
        $this->config = array_merge([
            'title' => 'API Documentation',
            'version' => '1.0.0',
            'description' => 'RESTful API for content management',
            'base_url' => 'http://localhost',
        ], $config);
    }
    
    /**
     * Generate OpenAPI specification
     *
     * @return array OpenAPI specification array
     */
    public function generate(): array
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $this->config['title'],
                'version' => $this->config['version'],
                'description' => $this->config['description'],
            ],
            'servers' => [
                [
                    'url' => $this->config['base_url'],
                    'description' => 'API Server',
                ],
            ],
            'paths' => $this->generatePaths(),
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
                'schemas' => $this->generateSchemas(),
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
        ];
        
        return $spec;
    }
    
    /**
     * Generate paths from routes
     *
     * @return array
     */
    private function generatePaths(): array
    {
        $paths = [];
        $routes = $this->router->getRoutes();
        
        foreach ($routes as $route) {
            $path = $this->convertPathToOpenAPI($route->getPath());
            $method = strtolower($route->getMethod());
            
            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }
            
            $paths[$path][$method] = $this->generateOperation($route);
        }
        
        return $paths;
    }
    
    /**
     * Convert route path to OpenAPI format
     *
     * @param string $path
     * @return string
     */
    private function convertPathToOpenAPI(string $path): string
    {
        // Convert {id} to {id} (already in correct format)
        return $path;
    }
    
    /**
     * Generate operation object for a route
     *
     * @param Route $route
     * @return array
     */
    private function generateOperation(Route $route): array
    {
        $operation = [
            'summary' => $this->generateSummary($route),
            'description' => $this->generateDescription($route),
            'tags' => $this->generateTags($route),
            'parameters' => $this->generateParameters($route),
            'responses' => $this->generateResponses($route),
        ];
        
        // Add request body for POST/PUT/PATCH
        if (in_array($route->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            $operation['requestBody'] = $this->generateRequestBody($route);
        }
        
        return $operation;
    }
    
    /**
     * Generate description with code examples
     *
     * @param Route $route
     * @return string
     */
    private function generateDescription(Route $route): string
    {
        $examples = $this->generateCodeExamples($route);
        
        $description = "## Code Examples\n\n";
        
        foreach ($examples as $lang => $code) {
            $description .= "### {$lang}\n\n```{$this->getLanguageTag($lang)}\n{$code}\n```\n\n";
        }
        
        return $description;
    }
    
    /**
     * Generate code examples for route
     *
     * @param Route $route
     * @return array
     */
    private function generateCodeExamples(Route $route): array
    {
        $method = $route->getMethod();
        $path = $route->getPath();
        $baseUrl = $this->config['base_url'];
        
        // Replace path parameters with example values
        $examplePath = preg_replace('/\{id\}/', '1', $path);
        $examplePath = preg_replace('/\{([^}]+)\}/', 'example', $examplePath);
        
        $fullUrl = $baseUrl . $examplePath;
        
        return [
            'cURL' => $this->generateCurlExample($method, $fullUrl, $route),
            'PHP' => $this->generatePhpExample($method, $fullUrl, $route),
            'JavaScript' => $this->generateJavaScriptExample($method, $fullUrl, $route),
        ];
    }
    
    /**
     * Generate cURL example
     *
     * @param string $method
     * @param string $url
     * @param Route $route
     * @return string
     */
    private function generateCurlExample(string $method, string $url, Route $route): string
    {
        $curl = "curl -X {$method} \\\n";
        $curl .= "  '{$url}' \\\n";
        $curl .= "  -H 'Authorization: Bearer YOUR_TOKEN' \\\n";
        $curl .= "  -H 'Content-Type: application/json'";
        
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $curl .= " \\\n  -d '" . json_encode($this->getExampleRequestBody($route), JSON_PRETTY_PRINT) . "'";
        }
        
        return $curl;
    }
    
    /**
     * Generate PHP example
     *
     * @param string $method
     * @param string $url
     * @param Route $route
     * @return string
     */
    private function generatePhpExample(string $method, string $url, Route $route): string
    {
        $php = "\$client = new GuzzleHttp\\Client();\n\n";
        $php .= "\$response = \$client->request('{$method}', '{$url}', [\n";
        $php .= "    'headers' => [\n";
        $php .= "        'Authorization' => 'Bearer YOUR_TOKEN',\n";
        $php .= "        'Content-Type' => 'application/json',\n";
        $php .= "    ],\n";
        
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $php .= "    'json' => " . var_export($this->getExampleRequestBody($route), true) . ",\n";
        }
        
        $php .= "]);\n\n";
        $php .= "\$data = json_decode(\$response->getBody(), true);";
        
        return $php;
    }
    
    /**
     * Generate JavaScript example
     *
     * @param string $method
     * @param string $url
     * @param Route $route
     * @return string
     */
    private function generateJavaScriptExample(string $method, string $url, Route $route): string
    {
        $js = "const response = await fetch('{$url}', {\n";
        $js .= "  method: '{$method}',\n";
        $js .= "  headers: {\n";
        $js .= "    'Authorization': 'Bearer YOUR_TOKEN',\n";
        $js .= "    'Content-Type': 'application/json',\n";
        $js .= "  },\n";
        
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $js .= "  body: JSON.stringify(" . json_encode($this->getExampleRequestBody($route), JSON_PRETTY_PRINT) . "),\n";
        }
        
        $js .= "});\n\n";
        $js .= "const data = await response.json();";
        
        return $js;
    }
    
    /**
     * Get example request body
     *
     * @param Route $route
     * @return array
     */
    private function getExampleRequestBody(Route $route): array
    {
        $path = $route->getPath();
        
        if (str_contains($path, 'content')) {
            return [
                'title' => 'Example Post',
                'content' => 'This is example content',
                'status' => 'published',
            ];
        }
        
        if (str_contains($path, 'user')) {
            return [
                'email' => 'user@example.com',
                'password' => 'secure-password',
                'displayName' => 'John Doe',
            ];
        }
        
        return ['example' => 'data'];
    }
    
    /**
     * Get language tag for code block
     *
     * @param string $lang
     * @return string
     */
    private function getLanguageTag(string $lang): string
    {
        return match($lang) {
            'cURL' => 'bash',
            'PHP' => 'php',
            'JavaScript' => 'javascript',
            default => 'text',
        };
    }
    
    /**
     * Generate summary from route
     *
     * @param Route $route
     * @return string
     */
    private function generateSummary(Route $route): string
    {
        $method = $route->getMethod();
        $path = $route->getPath();
        
        // Extract resource name from path
        $parts = explode('/', trim($path, '/'));
        $resource = $parts[count($parts) - 1] ?? 'resource';
        
        // Remove {id} from resource name
        $resource = preg_replace('/\{.*?\}/', '', $resource);
        $resource = trim($resource, '/');
        
        return match($method) {
            'GET' => str_contains($path, '{') ? "Get {$resource} by ID" : "List {$resource}",
            'POST' => "Create {$resource}",
            'PUT', 'PATCH' => "Update {$resource}",
            'DELETE' => "Delete {$resource}",
            default => "Operation on {$resource}",
        };
    }
    
    /**
     * Generate tags from route path
     *
     * @param Route $route
     * @return array
     */
    private function generateTags(Route $route): array
    {
        $path = $route->getPath();
        $parts = explode('/', trim($path, '/'));
        
        // Use first path segment as tag (e.g., /api/contents -> Contents)
        $tag = $parts[1] ?? 'default';
        $tag = ucfirst($tag);
        
        return [$tag];
    }
    
    /**
     * Generate parameters from route
     *
     * @param Route $route
     * @return array
     */
    private function generateParameters(Route $route): array
    {
        $parameters = [];
        $path = $route->getPath();
        
        // Extract path parameters
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        
        foreach ($matches[1] as $param) {
            $parameters[] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => $param === 'id' ? 'integer' : 'string',
                ],
            ];
        }
        
        // Add query parameters for GET list endpoints
        if ($route->getMethod() === 'GET' && !str_contains($path, '{')) {
            $parameters[] = [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'default' => 1],
            ];
            $parameters[] = [
                'name' => 'limit',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'default' => 20],
            ];
        }
        
        return $parameters;
    }
    
    /**
     * Generate request body for route
     *
     * @param Route $route
     * @return array
     */
    private function generateRequestBody(Route $route): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $this->inferRequestProperties($route),
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Infer request properties from route
     *
     * @param Route $route
     * @return array
     */
    private function inferRequestProperties(Route $route): array
    {
        $path = $route->getPath();
        
        // Basic inference based on path
        if (str_contains($path, 'content')) {
            return [
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
            ];
        }
        
        if (str_contains($path, 'user')) {
            return [
                'email' => ['type' => 'string', 'format' => 'email'],
                'password' => ['type' => 'string', 'format' => 'password'],
                'displayName' => ['type' => 'string'],
            ];
        }
        
        return [];
    }
    
    /**
     * Generate responses for route
     *
     * @param Route $route
     * @return array
     */
    private function generateResponses(Route $route): array
    {
        $method = $route->getMethod();
        
        $responses = [
            '200' => [
                'description' => 'Successful operation',
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object'],
                    ],
                ],
            ],
            '400' => [
                'description' => 'Bad request',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                    ],
                ],
            ],
            '401' => [
                'description' => 'Unauthorized',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Error'],
                    ],
                ],
            ],
        ];
        
        if ($method === 'POST') {
            $responses['201'] = $responses['200'];
            $responses['201']['description'] = 'Resource created';
            unset($responses['200']);
        }
        
        if ($method === 'DELETE') {
            $responses['204'] = ['description' => 'Resource deleted'];
            unset($responses['200']);
        }
        
        return $responses;
    }
    
    /**
     * Generate common schemas
     *
     * @return array
     */
    private function generateSchemas(): array
    {
        return [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                            'details' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
            'Content' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                    'type' => ['type' => 'string', 'enum' => ['post', 'page', 'custom']],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'published', 'scheduled', 'trash']],
                    'authorId' => ['type' => 'integer'],
                    'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                    'updatedAt' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'displayName' => ['type' => 'string'],
                    'role' => ['type' => 'string', 'enum' => ['admin', 'editor', 'author', 'subscriber']],
                    'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Media' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'filename' => ['type' => 'string'],
                    'path' => ['type' => 'string'],
                    'mimeType' => ['type' => 'string'],
                    'size' => ['type' => 'integer'],
                    'url' => ['type' => 'string', 'format' => 'uri'],
                    'uploadedAt' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
        ];
    }
    
    /**
     * Generate JSON string
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Save specification to file
     *
     * @param string $path
     * @return bool
     */
    public function saveToFile(string $path): bool
    {
        return file_put_contents($path, $this->toJson()) !== false;
    }
}
