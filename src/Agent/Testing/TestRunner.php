<?php

/**
 * A2T Test Runner — declarative test execution engine.
 *
 * Agents define tests using a fixed catalog of assertions.
 * No code generation, no eval, no PHPUnit — just declarative JSON.
 *
 * Assertion types:
 *   entity_exists       — entity schema is materialized
 *   entity_not_exists   — entity is not defined
 *   insert_succeeds     — insert returns an ID (no validation errors)
 *   insert_fails        — insert returns validation errors
 *   enum_rejects        — enum field rejects invalid value
 *   find_returns        — find by ID returns expected fields
 *   filter_count        — findAll with filter returns expected count
 *   update_changes      — update modifies the specified fields
 *   delete_removes      — delete makes record unfindable
 *   workflow_succeeds   — workflow execution completes without errors
 *   workflow_produces   — workflow produces expected data in store
 *   search_finds        — semantic search returns the expected record
 *   tool_returns        — tool execution returns expected shape
 *   response_contains   — result contains expected key/value
 */

declare(strict_types=1);

namespace Framework\Agent\Testing;

use Framework\Agent\Data\EntityMaterializer;
use Framework\Agent\Workflow\WorkflowEngine;
use Framework\Agent\AgentService;

class TestRunner
{
    private ?EntityMaterializer $materializer;
    private ?WorkflowEngine $workflow;
    private ?AgentService $agent;

    public function __construct(
        ?EntityMaterializer $materializer = null,
        ?WorkflowEngine $workflow = null,
        ?AgentService $agent = null,
    ) {
        $this->materializer = $materializer;
        $this->workflow     = $workflow;
        $this->agent        = $agent;
    }

    /**
     * Run a test suite.
     *
     * @param array $suite Suite definition with 'name' and 'tests'.
     * @return array Results with pass/fail per test and summary.
     */
    public function run(array $suite): array
    {
        $name    = $suite['name'] ?? $suite['suite'] ?? 'unnamed';
        $tests   = $suite['tests'] ?? [];
        $results = [];
        $passed  = 0;
        $failed  = 0;
        $start   = microtime(true);

        // Setup: track IDs created so we can cleanup
        $cleanup = [];

        foreach ($tests as $i => $test) {
            $assert = $test['assert'] ?? '';
            $label  = $test['label'] ?? "{$assert} #{$i}";

            try {
                $result = $this->executeAssertion($test, $cleanup);
                $pass   = $result['pass'];

                if ($pass) {
                    $passed++;
                } else {
                    $failed++;
                }

                $results[] = [
                    'label'  => $label,
                    'assert' => $assert,
                    'pass'   => $pass,
                    'detail' => $result['detail'] ?? null,
                ];
            } catch (\Throwable $e) {
                $failed++;
                $results[] = [
                    'label'  => $label,
                    'assert' => $assert,
                    'pass'   => false,
                    'detail' => 'Exception: ' . $e->getMessage(),
                ];
            }
        }

        // Cleanup created records
        $this->cleanup($cleanup);

        return [
            'suite'       => $name,
            'total'       => count($tests),
            'passed'      => $passed,
            'failed'      => $failed,
            'success'     => $failed === 0,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            'results'     => $results,
        ];
    }

    /**
     * Run multiple suites.
     */
    public function runAll(array $suites): array
    {
        $results = [];
        $totalPassed = 0;
        $totalFailed = 0;

        foreach ($suites as $suite) {
            $result = $this->run($suite);
            $results[] = $result;
            $totalPassed += $result['passed'];
            $totalFailed += $result['failed'];
        }

        return [
            'suites'       => count($results),
            'total_passed' => $totalPassed,
            'total_failed' => $totalFailed,
            'success'      => $totalFailed === 0,
            'results'      => $results,
        ];
    }

    // ── Assertions ─────────────────────────────────────────────────

    private function executeAssertion(array $test, array &$cleanup): array
    {
        $assert = $test['assert'] ?? '';

        return match ($assert) {
            'entity_exists'     => $this->assertEntityExists($test),
            'entity_not_exists' => $this->assertEntityNotExists($test),
            'insert_succeeds'   => $this->assertInsertSucceeds($test, $cleanup),
            'insert_fails'      => $this->assertInsertFails($test),
            'enum_rejects'      => $this->assertEnumRejects($test),
            'find_returns'      => $this->assertFindReturns($test),
            'filter_count'      => $this->assertFilterCount($test),
            'update_changes'    => $this->assertUpdateChanges($test, $cleanup),
            'delete_removes'    => $this->assertDeleteRemoves($test, $cleanup),
            'workflow_succeeds' => $this->assertWorkflowSucceeds($test),
            'workflow_produces' => $this->assertWorkflowProduces($test),
            'tool_returns'      => $this->assertToolReturns($test),
            'response_contains' => $this->assertResponseContains($test),
            default             => ['pass' => false, 'detail' => "Unknown assertion: {$assert}"],
        };
    }

    private function assertEntityExists(array $test): array
    {
        $entity = $test['entity'] ?? '';
        $schema = $this->materializer?->getSchema($entity);
        return [
            'pass'   => null !== $schema,
            'detail' => $schema ? "{$entity}: " . count($schema->fields) . " fields" : "{$entity} not found",
        ];
    }

    private function assertEntityNotExists(array $test): array
    {
        $entity = $test['entity'] ?? '';
        $schema = $this->materializer?->getSchema($entity);
        return [
            'pass'   => null === $schema,
            'detail' => null === $schema ? "{$entity} correctly absent" : "{$entity} unexpectedly exists",
        ];
    }

    private function assertInsertSucceeds(array $test, array &$cleanup): array
    {
        $entity = $test['entity'] ?? '';
        $data   = $test['data'] ?? [];

        $result = $this->materializer?->insert($entity, $data);
        $pass   = is_array($result) && isset($result['id']) && !isset($result['error']);

        if ($pass) {
            $cleanup[] = ['entity' => $entity, 'id' => $result['id']];
        }

        return [
            'pass'   => $pass,
            'detail' => $pass ? "Inserted ID {$result['id']}" : json_encode($result),
        ];
    }

    private function assertInsertFails(array $test): array
    {
        $entity = $test['entity'] ?? '';
        $data   = $test['data'] ?? [];

        $result = $this->materializer?->insert($entity, $data);
        $pass   = is_array($result) && isset($result['error']);

        return [
            'pass'   => $pass,
            'detail' => $pass ? "Correctly rejected: {$result['error']}" : "Unexpectedly succeeded",
        ];
    }

    private function assertEnumRejects(array $test): array
    {
        $entity = $test['entity'] ?? '';
        $field  = $test['field'] ?? '';
        $value  = $test['value'] ?? '';

        // Build minimal valid data with invalid enum
        $schema = $this->materializer?->getSchema($entity);
        if (!$schema) return ['pass' => false, 'detail' => "Entity {$entity} not found"];

        $data = [];
        foreach ($schema->fields as $f) {
            if ($f['name'] === $field) {
                $data[$f['name']] = $value; // invalid value
            } elseif ($f['required'] ?? false) {
                $data[$f['name']] = match ($f['type']) {
                    'integer', 'number' => 1,
                    'boolean' => true,
                    'enum'    => ($f['values'][0] ?? 'default'),
                    default   => 'test_value',
                };
            }
        }

        $result = $this->materializer?->insert($entity, $data);
        $pass   = is_array($result) && isset($result['error']);

        return [
            'pass'   => $pass,
            'detail' => $pass ? "Enum {$field}={$value} correctly rejected" : "Unexpectedly accepted",
        ];
    }

    private function assertFindReturns(array $test): array
    {
        $entity = $test['entity'] ?? '';
        $id     = (int) ($test['id'] ?? 0);
        $expect = $test['expect'] ?? [];

        $record = $this->materializer?->find($entity, $id);
        if (!$record) return ['pass' => false, 'detail' => "Record {$id} not found"];

        foreach ($expect as $key => $value) {
            if (($record[$key] ?? null) != $value) {
                return ['pass' => false, 'detail' => "Expected {$key}={$value}, got " . ($record[$key] ?? 'null')];
            }
        }

        return ['pass' => true, 'detail' => "Record {$id} matches expectations"];
    }

    private function assertFilterCount(array $test): array
    {
        $entity  = $test['entity'] ?? '';
        $filters = $test['filters'] ?? [];
        $expect  = (int) ($test['count'] ?? 0);

        $results = $this->materializer?->findAll($entity, $filters);
        $actual  = count($results);

        return [
            'pass'   => $actual === $expect,
            'detail' => "Expected {$expect}, got {$actual}",
        ];
    }

    private function assertUpdateChanges(array $test, array &$cleanup): array
    {
        $entity = $test['entity'] ?? '';
        $id     = (int) ($test['id'] ?? 0);
        $data   = $test['data'] ?? [];
        $expect = $test['expect'] ?? $data;

        $this->materializer?->update($entity, $id, $data);
        $record = $this->materializer?->find($entity, $id);

        if (!$record) return ['pass' => false, 'detail' => "Record {$id} not found after update"];

        foreach ($expect as $key => $value) {
            if (($record[$key] ?? null) != $value) {
                return ['pass' => false, 'detail' => "Expected {$key}={$value} after update, got " . ($record[$key] ?? 'null')];
            }
        }

        return ['pass' => true, 'detail' => "Record {$id} updated correctly"];
    }

    private function assertDeleteRemoves(array $test, array &$cleanup): array
    {
        $entity = $test['entity'] ?? '';
        $id     = (int) ($test['id'] ?? 0);

        $this->materializer?->delete($entity, $id);
        $record = $this->materializer?->find($entity, $id);

        // Remove from cleanup since we already deleted
        $cleanup = array_filter($cleanup, fn($c) => !($c['entity'] === $entity && $c['id'] === $id));

        return [
            'pass'   => null === $record || false === $record,
            'detail' => $record ? "Record {$id} still exists" : "Record {$id} deleted",
        ];
    }

    private function assertWorkflowSucceeds(array $test): array
    {
        if (!$this->workflow) return ['pass' => false, 'detail' => 'No workflow engine'];

        $steps  = $test['steps'] ?? [];
        $input  = $test['input'] ?? null;
        $result = $this->workflow->run($steps, $input);

        return [
            'pass'   => $result['success'] ?? false,
            'detail' => ($result['success'] ?? false)
                ? "Workflow completed in {$result['duration_ms']}ms"
                : "Workflow failed: " . json_encode($result['errors'] ?? []),
        ];
    }

    private function assertWorkflowProduces(array $test): array
    {
        if (!$this->workflow) return ['pass' => false, 'detail' => 'No workflow engine'];

        $steps  = $test['steps'] ?? [];
        $input  = $test['input'] ?? null;
        $expect = $test['expect'] ?? [];
        $result = $this->workflow->run($steps, $input);
        $store  = $result['store'] ?? [];

        foreach ($expect as $key => $value) {
            if ('*' === $value) {
                if (!array_key_exists($key, $store)) {
                    return ['pass' => false, 'detail' => "Expected key '{$key}' in store"];
                }
            } elseif (($store[$key] ?? null) != $value) {
                return ['pass' => false, 'detail' => "Expected store[{$key}]={$value}, got " . json_encode($store[$key] ?? null)];
            }
        }

        return ['pass' => true, 'detail' => 'Workflow produced expected data'];
    }

    private function assertToolReturns(array $test): array
    {
        if (!$this->agent) return ['pass' => false, 'detail' => 'No agent'];

        $tool   = $test['tool'] ?? '';
        $args   = $test['args'] ?? [];
        $expect = $test['expect'] ?? [];

        $result = $this->agent->invokeToolByName($tool, $args);

        if (is_array($result) && isset($result['error'])) {
            return ['pass' => false, 'detail' => "Tool error: {$result['error']}"];
        }

        if (!empty($expect) && is_array($result)) {
            foreach ($expect as $key => $value) {
                if ('*' === $value) {
                    if (!array_key_exists($key, $result)) {
                        return ['pass' => false, 'detail' => "Expected key '{$key}' in result"];
                    }
                }
            }
        }

        return ['pass' => true, 'detail' => 'Tool returned expected shape'];
    }

    private function assertResponseContains(array $test): array
    {
        $data   = $test['data'] ?? [];
        $expect = $test['expect'] ?? [];

        foreach ($expect as $key => $value) {
            if (!array_key_exists($key, $data)) {
                return ['pass' => false, 'detail' => "Missing key '{$key}'"];
            }
            if ('*' !== $value && $data[$key] != $value) {
                return ['pass' => false, 'detail' => "Expected {$key}={$value}, got {$data[$key]}"];
            }
        }

        return ['pass' => true, 'detail' => 'Response contains expected data'];
    }

    // ── Cleanup ────────────────────────────────────────────────────

    private function cleanup(array $records): void
    {
        foreach ($records as $r) {
            try {
                $this->materializer?->delete($r['entity'], $r['id']);
            } catch (\Throwable) {
                // Ignore cleanup errors
            }
        }
    }
}
