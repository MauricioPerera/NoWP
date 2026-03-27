<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Workflow;

/**
 * In-memory data store for workflow execution.
 */
class DataStore
{
    private array $data = [];

    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
    public function get(string $key): mixed { return $this->data[$key] ?? null; }
    public function has(string $key): bool { return array_key_exists($key, $this->data); }
    public function all(): array { return $this->data; }

    /**
     * Resolve a value — if string starts with /, look up in store.
     */
    public function resolve(mixed $value): mixed
    {
        if (!is_string($value) || !str_starts_with($value, '/')) {
            if (is_array($value)) {
                return array_map(fn($v) => $this->resolve($v), $value);
            }
            return $value;
        }

        $path = substr($value, 1);
        $parts = explode('.', $path, 2);
        $data = $this->get($parts[0]);

        if (null === $data || !isset($parts[1])) {
            return $data;
        }

        // Dot-notation traversal
        foreach (explode('.', $parts[1]) as $key) {
            if ('length' === $key && is_array($data)) return count($data);
            if (is_array($data) && array_key_exists($key, $data)) $data = $data[$key];
            elseif (is_array($data) && is_numeric($key)) $data = $data[(int)$key] ?? null;
            else return null;
        }

        return $data;
    }
}
