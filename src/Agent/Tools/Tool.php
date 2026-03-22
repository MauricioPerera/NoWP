<?php

declare(strict_types=1);

namespace Framework\Agent\Tools;

/**
 * Tool definition — a callable function the agent can invoke.
 */
class Tool
{
    private string $name;
    private string $description;
    private array $parameters;
    private \Closure $callable;

    public function __construct(string $name, string $description, ?\Closure $callable = null)
    {
        $this->name        = $name;
        $this->description = $description;
        $this->parameters  = ['type' => 'object', 'properties' => new \stdClass()];
        $this->callable    = $callable ?? fn() => null;
    }

    public static function make(string $name, string $description): self
    {
        return new self($name, $description);
    }

    public function param(string $name, string $type, string $description, bool $required = false): self
    {
        $props = (array) $this->parameters['properties'];
        $props[$name] = ['type' => $type, 'description' => $description];

        $this->parameters['properties'] = $props;

        if ($required) {
            $this->parameters['required']   = $this->parameters['required'] ?? [];
            $this->parameters['required'][] = $name;
        }

        return $this;
    }

    public function handler(\Closure $callable): self
    {
        $this->callable = $callable;
        return $this;
    }

    public function execute(array $args = []): mixed
    {
        return ($this->callable)(...array_values($args));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toSchema(): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'parameters'  => $this->parameters,
        ];
    }
}
