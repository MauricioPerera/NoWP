<?php

/**
 * Documentation Controller
 * 
 * Serves API documentation with Swagger UI.
 * 
 * Requirements: 13.2
 */

declare(strict_types=1);

namespace ChimeraNoWP\Core;

class DocsController
{
    private OpenAPIGenerator $generator;
    
    public function __construct(OpenAPIGenerator $generator)
    {
        $this->generator = $generator;
    }
    
    /**
     * Serve Swagger UI HTML page
     *
     * @return Response
     */
    public function index(): Response
    {
        $html = $this->getSwaggerUIHtml();
        
        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }
    
    /**
     * Serve OpenAPI specification JSON
     *
     * @return Response
     */
    public function spec(): Response
    {
        return Response::json($this->generator->generate());
    }
    
    /**
     * Get Swagger UI HTML
     *
     * @return string
     */
    private function getSwaggerUIHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.10.5/swagger-ui.css">
    <style>
        body {
            margin: 0;
            padding: 0;
        }
        .swagger-ui .topbar {
            display: none;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.10.5/swagger-ui-bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.10.5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: '/api/docs/spec',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                persistAuthorization: true,
                tryItOutEnabled: true,
            });
            
            window.ui = ui;
        };
    </script>
</body>
</html>
HTML;
    }
}
