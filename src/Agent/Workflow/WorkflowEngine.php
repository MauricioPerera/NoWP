<?php

declare(strict_types=1);

namespace Framework\Agent\Workflow;

use Framework\Agent\Tools\Tool;

/**
 * Workflow Engine — executes multi-step workflows with A2E operations.
 *
 * Operations: ExecuteTool, FilterData, TransformData, Conditional,
 * Loop, StoreData, Wait, MergeData.
 */
class WorkflowEngine
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function registerTool(Tool $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /**
     * @param Tool[] $tools
     */
    public function registerTools(array $tools): void
    {
        foreach ($tools as $tool) {
            $this->registerTool($tool);
        }
    }

    /**
     * Execute a workflow.
     *
     * @param array      $steps   Array of step definitions.
     * @param array|null $initial Initial data store values.
     * @return array Execution result.
     */
    public function run(array $steps, ?array $initial = null): array
    {
        $store  = new DataStore();
        $start  = microtime(true);
        $errors = [];
        $ran    = 0;

        if ($initial) {
            foreach ($initial as $k => $v) {
                $store->set($k, $v);
            }
        }

        foreach ($steps as $step) {
            $id   = $step['id'] ?? 'step_' . $ran;
            $type = $step['type'] ?? '';

            $result = $this->executeStep($step, $store);
            $ran++;

            if ($result instanceof \RuntimeException) {
                $errors[] = ['step' => $id, 'type' => $type, 'message' => $result->getMessage()];
                $store->set($id, ['error' => $result->getMessage()]);
                if (empty($step['continue_on_error'])) break;
                continue;
            }

            $store->set($id, $result);
        }

        return [
            'success'     => empty($errors),
            'store'       => $store->all(),
            'steps_run'   => $ran,
            'errors'      => $errors,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ];
    }

    private function executeStep(array $step, DataStore $store): mixed
    {
        $type = $step['type'] ?? '';

        return match ($type) {
            'ExecuteTool'   => $this->opExecuteTool($step, $store),
            'FilterData'    => $this->opFilter($step, $store),
            'TransformData' => $this->opTransform($step, $store),
            'Conditional'   => $this->opConditional($step, $store),
            'Loop'          => $this->opLoop($step, $store),
            'StoreData'     => $this->opStore($step, $store),
            'Wait'          => $this->opWait($step, $store),
            'MergeData'     => $this->opMerge($step, $store),
            default         => new \RuntimeException("Unknown operation: {$type}"),
        };
    }

    private function opExecuteTool(array $step, DataStore $store): mixed
    {
        $name = $step['tool'] ?? '';
        $tool = $this->tools[$name] ?? null;
        if (!$tool) {
            return new \RuntimeException("Tool '{$name}' not found.");
        }

        $input = isset($step['input']) ? $store->resolve($step['input']) : [];
        return $tool->execute(is_array($input) ? $input : ['input' => $input]);
    }

    private function opFilter(array $step, DataStore $store): mixed
    {
        $data  = $store->resolve($step['data'] ?? '');
        if (!is_array($data)) return new \RuntimeException('FilterData requires array data.');

        $field = $step['field'] ?? '';
        $op    = $step['operator'] ?? 'eq';
        $value = isset($step['value']) ? $store->resolve($step['value']) : null;

        return array_values(array_filter($data, function ($item) use ($field, $op, $value) {
            $v = is_array($item) ? ($item[$field] ?? null) : null;
            return match ($op) {
                'eq'       => $v == $value,
                'neq'      => $v != $value,
                'gt'       => $v > $value,
                'lt'       => $v < $value,
                'contains' => is_string($v) && str_contains($v, (string)$value),
                'exists'   => null !== $v,
                'empty'    => empty($v),
                default    => false,
            };
        }));
    }

    private function opTransform(array $step, DataStore $store): mixed
    {
        $data = $store->resolve($step['data'] ?? '');
        if (!is_array($data)) return new \RuntimeException('TransformData requires array.');

        return match ($step['operation'] ?? '') {
            'map'     => array_map(fn($i) => is_array($i) ? ($i[$step['field'] ?? ''] ?? null) : $i, $data),
            'sort'    => (function () use ($data, $step) {
                $f = $step['field'] ?? ''; $o = $step['order'] ?? 'asc';
                usort($data, fn($a, $b) => ($o === 'desc' ? -1 : 1) * (($a[$f] ?? 0) <=> ($b[$f] ?? 0)));
                return $data;
            })(),
            'count'   => count($data),
            'unique'  => array_values(array_unique($data)),
            'reverse' => array_reverse($data),
            'slice'   => array_slice($data, (int)($step['offset'] ?? 0), (int)($step['limit'] ?? 10)),
            'select'  => array_map(fn($i) => array_intersect_key($i, array_flip($step['fields'] ?? [])), $data),
            'group'   => (function () use ($data, $step) {
                $f = $step['field'] ?? ''; $g = [];
                foreach ($data as $i) { $g[$i[$f] ?? '_'][] = $i; }
                return $g;
            })(),
            default => new \RuntimeException("Unknown transform: {$step['operation']}"),
        };
    }

    private function opConditional(array $step, DataStore $store): mixed
    {
        $left  = $store->resolve($step['left'] ?? '');
        $right = isset($step['right']) ? $store->resolve($step['right']) : null;
        $op    = $step['operator'] ?? 'truthy';

        $result = match ($op) {
            'eq'     => $left == $right,
            'neq'    => $left != $right,
            'gt'     => $left > $right,
            'lt'     => $left < $right,
            'truthy' => !empty($left),
            'falsy'  => empty($left),
            default  => false,
        };

        $branch = $result ? ($step['then'] ?? []) : ($step['else'] ?? []);
        if (empty($branch)) return ['condition' => $result, 'executed' => false];

        $sub = $this->run($branch);
        return ['condition' => $result, 'executed' => true, 'result' => $sub['store']];
    }

    private function opLoop(array $step, DataStore $store): mixed
    {
        $data = $store->resolve($step['data'] ?? '');
        if (!is_array($data)) return new \RuntimeException('Loop requires array.');

        $body  = $step['steps'] ?? [];
        $as    = $step['as'] ?? '_item';
        $limit = min(count($data), 1000);
        $results = [];

        for ($i = 0; $i < $limit; $i++) {
            $store->set($as, $data[$i]);
            $store->set('_index', $i);
            $sub = $this->run($body);
            $results[] = $sub['store'];
        }

        return $results;
    }

    private function opStore(array $step, DataStore $store): mixed
    {
        $key   = $step['key'] ?? $step['id'] ?? '';
        $value = isset($step['value']) ? $store->resolve($step['value']) : null;
        $store->set($key, $value);
        return ['stored' => $key];
    }

    private function opWait(array $step, DataStore $store): array
    {
        $seconds = min((int)$store->resolve($step['seconds'] ?? 1), 30);
        if ($seconds > 0) usleep($seconds * 1_000_000);
        return ['waited' => $seconds];
    }

    private function opMerge(array $step, DataStore $store): mixed
    {
        $sources = array_map(fn($s) => $store->resolve($s), $step['sources'] ?? []);
        return match ($step['mode'] ?? 'concat') {
            'concat' => array_merge(...array_filter($sources, 'is_array')),
            'zip'    => array_map(null, ...$sources),
            default  => array_merge(...array_filter($sources, 'is_array')),
        };
    }
}
