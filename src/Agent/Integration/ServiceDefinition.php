<?php

/**
 * A2I Service Definition — declarative external service integration.
 *
 * An agent declares a service (base URL, auth, endpoints) and the system
 * materializes: stored credentials, tools per endpoint, and connection test.
 */

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Integration;

class ServiceDefinition
{
    public readonly string $name;
    public readonly string $label;
    public readonly string $baseUrl;
    public readonly array $auth;
    public readonly array $endpoints;
    public readonly array $test;
    public readonly string $createdAt;

    public function __construct(array $definition)
    {
        $this->name      = self::sanitize($definition['service'] ?? $definition['name'] ?? '');
        $this->label     = $definition['label'] ?? ucfirst(str_replace('_', ' ', $this->name));
        $this->baseUrl   = rtrim($definition['base_url'] ?? '', '/');
        $this->auth      = self::parseAuth($definition['auth'] ?? []);
        $this->endpoints = self::parseEndpoints($definition['endpoints'] ?? []);
        $this->test      = $definition['test'] ?? [];
        $this->createdAt = $definition['created_at'] ?? date('c');
    }

    public function toArray(): array
    {
        return [
            'name'       => $this->name,
            'label'      => $this->label,
            'base_url'   => $this->baseUrl,
            'auth'       => $this->auth,
            'endpoints'  => $this->endpoints,
            'test'       => $this->test,
            'created_at' => $this->createdAt,
        ];
    }

    private static function sanitize(string $name): string
    {
        return preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($name)));
    }

    private static function parseAuth(array $raw): array
    {
        return [
            'type'    => $raw['type'] ?? 'bearer',     // bearer, basic, header, query
            'key_env' => $raw['key_env'] ?? '',         // env var name
            'key'     => $raw['key'] ?? '',             // direct key (stored encrypted)
            'header'  => $raw['header'] ?? 'Authorization',
            'prefix'  => $raw['prefix'] ?? 'Bearer',
            'query_param' => $raw['query_param'] ?? 'api_key',
        ];
    }

    private static function parseEndpoints(array $raw): array
    {
        $endpoints = [];
        foreach ($raw as $ep) {
            $name = self::sanitize($ep['name'] ?? '');
            if ('' === $name) continue;

            $endpoints[] = [
                'name'        => $name,
                'description' => $ep['description'] ?? "Call {$name}",
                'method'      => strtoupper($ep['method'] ?? 'GET'),
                'path'        => $ep['path'] ?? '/',
                'params'      => $ep['params'] ?? [],       // query params
                'body'        => $ep['body'] ?? [],          // body fields
                'response_path' => $ep['response_path'] ?? '',  // dot-notation extraction
            ];
        }
        return $endpoints;
    }
}
