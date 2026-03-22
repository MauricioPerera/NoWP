<?php
/**
 * A2T Integration Test — tests the test runner itself.
 * Run: php tests/test-a2t.php
 */

spl_autoload_register(function ($class) {
    $prefix = 'Framework\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

use Framework\Agent\Data\EntitySchema;
use Framework\Agent\Data\EntityMaterializer;
use Framework\Agent\Workflow\WorkflowEngine;
use Framework\Agent\Testing\TestRunner;
use Framework\Database\Connection;

echo "A2T Integration Test\n";
echo "====================\n\n";

// Setup
$dbPath = sys_get_temp_dir() . '/nowp-a2t-test-' . uniqid() . '.db';
$conn = new Connection([
    'default' => 'sqlite',
    'connections' => ['sqlite' => [
        'driver' => 'sqlite', 'database' => $dbPath,
        'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
    ]],
]);

$materializer = new EntityMaterializer($conn);
$workflow     = new WorkflowEngine();
$runner       = new TestRunner($materializer, $workflow);

// Create a test entity first
$materializer->materialize(new EntitySchema([
    'entity' => 'task',
    'label'  => 'Task',
    'fields' => [
        ['name' => 'title',    'type' => 'string',  'required' => true],
        ['name' => 'status',   'type' => 'enum',    'values' => ['todo', 'doing', 'done'], 'required' => true],
        ['name' => 'priority', 'type' => 'integer', 'default' => 0],
    ],
]));

// Insert seed data
$materializer->insert('task', ['title' => 'Write docs', 'status' => 'todo', 'priority' => 1]);
$materializer->insert('task', ['title' => 'Fix bug', 'status' => 'doing', 'priority' => 3]);
$materializer->insert('task', ['title' => 'Deploy', 'status' => 'done', 'priority' => 2]);

// Run test suite
$result = $runner->run([
    'suite' => 'task-system',
    'tests' => [
        // Schema tests
        ['assert' => 'entity_exists',     'entity' => 'task',    'label' => 'Task entity exists'],
        ['assert' => 'entity_not_exists', 'entity' => 'unicorn', 'label' => 'Unicorn does not exist'],

        // Insert tests
        ['assert' => 'insert_succeeds', 'entity' => 'task', 'data' => ['title' => 'Test task', 'status' => 'todo'], 'label' => 'Insert valid task'],
        ['assert' => 'insert_fails',    'entity' => 'task', 'data' => ['priority' => 1],                            'label' => 'Reject missing required'],
        ['assert' => 'enum_rejects',    'entity' => 'task', 'field' => 'status', 'value' => 'INVALID',              'label' => 'Reject invalid enum'],

        // Query tests
        ['assert' => 'find_returns',  'entity' => 'task', 'id' => 1, 'expect' => ['title' => 'Write docs'],  'label' => 'Find task #1'],
        ['assert' => 'filter_count',  'entity' => 'task', 'filters' => ['status' => 'todo'], 'count' => 2,   'label' => 'Count todo tasks'],
        ['assert' => 'filter_count',  'entity' => 'task', 'filters' => ['status' => 'done'], 'count' => 1,   'label' => 'Count done tasks'],

        // Update test
        ['assert' => 'update_changes', 'entity' => 'task', 'id' => 1, 'data' => ['status' => 'doing'], 'expect' => ['status' => 'doing'], 'label' => 'Update task status'],

        // Delete test
        ['assert' => 'delete_removes', 'entity' => 'task', 'id' => 3, 'label' => 'Delete task #3'],

        // Workflow test
        ['assert' => 'workflow_succeeds', 'steps' => [
            ['id' => 'a', 'type' => 'StoreData', 'key' => 'x', 'value' => 42],
            ['id' => 'b', 'type' => 'StoreData', 'key' => 'y', 'value' => '/a.x'],
        ], 'label' => 'Simple workflow succeeds'],

        ['assert' => 'workflow_produces', 'steps' => [
            ['id' => 'data', 'type' => 'StoreData', 'key' => 'nums', 'value' => [3, 1, 4, 1, 5]],
            ['id' => 'count', 'type' => 'TransformData', 'data' => '/nums', 'operation' => 'count'],
        ], 'expect' => ['count' => 5], 'label' => 'Workflow produces count=5'],
    ],
]);

// Print results
echo "Suite: {$result['suite']}\n";
echo "Total: {$result['total']} | Passed: {$result['passed']} | Failed: {$result['failed']}\n";
echo "Duration: {$result['duration_ms']}ms\n\n";

foreach ($result['results'] as $r) {
    $icon = $r['pass'] ? '✅' : '❌';
    echo "  {$icon} {$r['label']} — {$r['detail']}\n";
}

echo "\n" . ($result['success'] ? '✅ All tests passed.' : '❌ Some tests failed.') . "\n";

// Cleanup
@unlink($dbPath);
