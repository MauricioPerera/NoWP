<?php

/**
 * A2I Integration Manager — materializes services into tools and credentials.
 *
 * Takes a ServiceDefinition and creates:
 * 1. Stored credential (encrypted in config/env or file)
 * 2. One Tool per endpoint (registered in AgentFacade)
 * 3. Connection test execution
 */

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Integration;

use ChimeraNoWP\Agent\Core\ToolDefinition;
use ChimeraNoWP\Agent\AgentFacade;

class IntegrationManager
{
    private string $storagePath;

    /** @var array<string, ServiceDefinition> */
    private array $services = [];

    public function __construct(string $storagePath)
    {
        $this->storagePath = rtrim($storagePath, '/');
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
        $this->loadServices();
    }

    /**
     * Integrate a service: store definition, create tools, test connection.
     */
    public function integrate(ServiceDefinition $service, AgentFacade $agent): array
    {
        // 1. Store credential
        $this->storeCredential($service);

        // 2. Create tools for each endpoint
        $tools = $this->createTools($service);
        foreach ($tools as $tool) {
            $agent->tools->register($tool);
        }

        // 3. Store service definition
        $this->services[$service->name] = $service;
        $this->persistServices();

        // 4. Test connection
        $testResult = null;
        if (!empty($service->test)) {
            $testResult = $this->testConnection($service);
        }

        return [
            'service'   => $service->name,
            'label'     => $service->label,
            'base_url'  => $service->baseUrl,
            'tools'     => count($tools),
            'endpoints' => array_map(fn($t) => $t->name, $tools),
            'test'      => $testResult,
            'status'    => 'integrated',
        ];
    }

    /**
     * Remove a service integration.
     */
    public function remove(string $name): array
    {
        if (!isset($this->services[$name])) {
            return ['error' => "Service '{$name}' not found."];
        }

        // Delete credential file
        $credFile = $this->storagePath . '/' . $name . '.key';
        if (file_exists($credFile)) {
            unlink($credFile);
        }

        unset($this->services[$name]);
        $this->persistServices();

        return ['service' => $name, 'status' => 'removed'];
    }

    /**
     * List all integrated services.
     */
    public function listServices(): array
    {
        return array_map(fn(ServiceDefinition $s) => $s->toArray(), $this->services);
    }

    /**
     * Get a service definition.
     */
    public function getService(string $name): ?ServiceDefinition
    {
        return $this->services[$name] ?? null;
    }

    /**
     * Re-register tools for all stored services (called on boot).
     */
    public function bootTools(AgentFacade $agent): void
    {
        foreach ($this->services as $service) {
            $tools = $this->createTools($service);
            foreach ($tools as $tool) {
                $agent->tools->register($tool);
            }
        }
    }

    /**
     * Test a service connection.
     */
    public function testConnection(ServiceDefinition $service): array
    {
        $test     = $service->test;
        $endpoint = $test['endpoint'] ?? '';
        $expect   = (int) ($test['expect_status'] ?? 200);

        if ('' === $endpoint) {
            return ['pass' => false, 'detail' => 'No test endpoint defined'];
        }

        // Find the endpoint definition
        $epDef = null;
        foreach ($service->endpoints as $ep) {
            if ($ep['name'] === $endpoint) {
                $epDef = $ep;
                break;
            }
        }

        if (!$epDef) {
            return ['pass' => false, 'detail' => "Test endpoint '{$endpoint}' not found"];
        }

        $url     = $service->baseUrl . $epDef['path'];
        $headers = $this->buildAuthHeaders($service);

        $context = stream_context_create(['http' => [
            'method'          => $epDef['method'],
            'header'          => implode("\r\n", array_map(fn($k, $v) => "{$k}: {$v}", array_keys($headers), $headers)),
            'timeout'         => 10,
            'ignore_errors'   => true,
        ]]);

        $response = @file_get_contents($url, false, $context);
        $status   = 0;

        if (isset($http_response_header[0])) {
            preg_match('/\d{3}/', $http_response_header[0], $m);
            $status = (int) ($m[0] ?? 0);
        }

        return [
            'pass'   => $status === $expect,
            'status' => $status,
            'detail' => $status === $expect
                ? "Connection OK (HTTP {$status})"
                : "Expected HTTP {$expect}, got {$status}",
        ];
    }

    // ── Tool Creation ──────────────────────────────────────────────

    /**
     * Create tools from service endpoints.
     */
    private function createTools(ServiceDefinition $service): array
    {
        $tools = [];

        foreach ($service->endpoints as $ep) {
            $toolName = "{$service->name}_{$ep['name']}";

            // Build JSON Schema parameters
            $properties = [];
            $required = [];
            foreach ($ep['params'] as $param) {
                $properties[$param] = ['type' => 'string', 'description' => "Query parameter: {$param}"];
            }
            foreach ($ep['body'] as $field) {
                $properties[$field] = ['type' => 'string', 'description' => "Body field: {$field}"];
                $required[] = $field;
            }
            $parameters = [
                'type' => 'object',
                'properties' => $properties,
            ];
            if (!empty($required)) {
                $parameters['required'] = $required;
            }

            $tool = new ToolDefinition(
                $toolName,
                $ep['description'],
                $parameters,
                $this->createEndpointHandler($service, $ep),
                category: 'integration',
            );

            $tools[] = $tool;
        }

        return $tools;
    }

    private function createEndpointHandler(ServiceDefinition $service, array $ep): \Closure
    {
        return function () use ($service, $ep) {
            $args = func_get_args();

            $url     = $service->baseUrl . $ep['path'];
            $method  = $ep['method'];
            $headers = $this->buildAuthHeaders($service);
            $headers['Content-Type'] = 'application/json';

            // Build query string from params
            $queryParts = [];
            $paramNames = $ep['params'];
            foreach ($paramNames as $i => $param) {
                $val = $args[$i] ?? null;
                if (null !== $val && '' !== $val) {
                    $queryParts[$param] = $val;
                }
            }
            if (!empty($queryParts)) {
                $url .= '?' . http_build_query($queryParts);
            }

            // Build body from body fields
            $body = null;
            if (!empty($ep['body'])) {
                $bodyData = [];
                $offset   = count($paramNames);
                foreach ($ep['body'] as $j => $field) {
                    $val = $args[$offset + $j] ?? null;
                    if (null !== $val) {
                        $bodyData[$field] = $val;
                    }
                }
                $body = json_encode($bodyData);
            }

            $context = stream_context_create(['http' => [
                'method'        => $method,
                'header'        => implode("\r\n", array_map(fn($k, $v) => "{$k}: {$v}", array_keys($headers), $headers)),
                'content'       => $body,
                'timeout'       => 15,
                'ignore_errors' => true,
            ]]);

            $response = @file_get_contents($url, false, $context);
            if (false === $response) {
                return ['error' => "Request failed to {$url}"];
            }

            $data = json_decode($response, true) ?? $response;

            // Extract via response_path
            if (!empty($ep['response_path']) && is_array($data)) {
                foreach (explode('.', $ep['response_path']) as $key) {
                    $data = is_numeric($key) ? ($data[(int)$key] ?? null) : ($data[$key] ?? null);
                    if (null === $data) break;
                }
            }

            return $data;
        };
    }

    // ── Auth ───────────────────────────────────────────────────────

    private function buildAuthHeaders(ServiceDefinition $service): array
    {
        $auth    = $service->auth;
        $key     = $this->resolveKey($service);
        $headers = [];

        match ($auth['type']) {
            'bearer' => $headers[$auth['header']] = "{$auth['prefix']} {$key}",
            'basic'  => $headers['Authorization'] = 'Basic ' . base64_encode($key),
            'header' => $headers[$auth['header']] = $key,
            default  => null,
        };

        return $headers;
    }

    private function resolveKey(ServiceDefinition $service): string
    {
        $auth = $service->auth;

        // 1. Environment variable
        if (!empty($auth['key_env'])) {
            $env = getenv($auth['key_env']);
            if (false !== $env && '' !== $env) return $env;
        }

        // 2. Stored credential file
        $credFile = $this->storagePath . '/' . $service->name . '.key';
        if (file_exists($credFile)) {
            return trim(file_get_contents($credFile));
        }

        // 3. Direct key from definition
        return $auth['key'] ?? '';
    }

    // ── Credential Storage ─────────────────────────────────────────

    private function storeCredential(ServiceDefinition $service): void
    {
        $key = $service->auth['key'] ?? '';
        if ('' === $key) return;

        $credFile = $this->storagePath . '/' . $service->name . '.key';
        file_put_contents($credFile, $key);
        chmod($credFile, 0600);
    }

    // ── Persistence ────────────────────────────────────────────────

    private function persistServices(): void
    {
        $data = array_map(fn(ServiceDefinition $s) => $s->toArray(), $this->services);
        file_put_contents(
            $this->storagePath . '/services.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function loadServices(): void
    {
        $file = $this->storagePath . '/services.json';
        if (!file_exists($file)) return;

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) return;

        foreach ($data as $name => $def) {
            $this->services[$name] = new ServiceDefinition($def);
        }
    }
}
