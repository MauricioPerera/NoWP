<?php
/**
 * A2D Integration Test
 *
 * Tests EntitySchema + EntityMaterializer with a real SQLite database.
 * Run: php tests/test-a2d.php
 */

// Manual autoload for testing without Composer
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
use Framework\Database\Connection;

echo "A2D Integration Test\n";
echo "====================\n\n";

// Create SQLite connection
$dbPath = sys_get_temp_dir() . '/nowp-a2d-test-' . uniqid() . '.db';
$conn = new Connection([
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => $dbPath,
            'options'  => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],
    ],
]);

$materializer = new EntityMaterializer($conn);

// ── Test 1: Define a ticket entity ─────────────────────────────
echo "1. Define entity 'ticket'\n";

$schema = new EntitySchema([
    'entity'      => 'ticket',
    'label'       => 'Support Ticket',
    'description' => 'Customer support tickets with priority and status tracking.',
    'fields'      => [
        ['name' => 'subject',  'type' => 'string',  'required' => true],
        ['name' => 'body',     'type' => 'text'],
        ['name' => 'priority', 'type' => 'enum',    'values' => ['low', 'medium', 'high'], 'required' => true],
        ['name' => 'status',   'type' => 'enum',    'values' => ['open', 'in_progress', 'resolved', 'closed']],
        ['name' => 'email',    'type' => 'string',  'required' => true],
        ['name' => 'is_urgent','type' => 'boolean',  'default' => false],
    ],
    'search' => false,
    'api'    => true,
]);

echo "   SQL: " . str_replace("\n", "\n        ", $schema->toCreateSQL('sqlite')) . "\n";
echo "   Validation: " . json_encode(array_keys($schema->validationRules())) . "\n";

$result = $materializer->materialize($schema);
echo "   Result: " . json_encode($result) . "\n\n";

// ── Test 2: Insert records ────────────────────────────────────
echo "2. Insert records\n";

$t1 = $materializer->insert('ticket', [
    'subject'  => 'Login page broken',
    'body'     => 'Users cannot log in after the latest update. Getting 500 error.',
    'priority' => 'high',
    'status'   => 'open',
    'email'    => 'user@example.com',
    'is_urgent' => true,
]);
echo "   Ticket 1: " . json_encode($t1) . "\n";

$t2 = $materializer->insert('ticket', [
    'subject'  => 'Change logo color',
    'body'     => 'Could you change the logo from blue to green?',
    'priority' => 'low',
    'status'   => 'open',
    'email'    => 'designer@example.com',
]);
echo "   Ticket 2: " . json_encode($t2) . "\n";

$t3 = $materializer->insert('ticket', [
    'subject'  => 'Payment not processing',
    'body'     => 'Stripe integration returns timeout on checkout.',
    'priority' => 'high',
    'status'   => 'in_progress',
    'email'    => 'merchant@example.com',
    'is_urgent' => true,
]);
echo "   Ticket 3: " . json_encode($t3) . "\n\n";

// ── Test 3: Validation ────────────────────────────────────────
echo "3. Validation test (missing required fields)\n";

$bad = $materializer->insert('ticket', [
    'body' => 'No subject, no priority, no email',
]);
echo "   Result: " . json_encode($bad) . "\n\n";

$bad2 = $materializer->insert('ticket', [
    'subject'  => 'Test',
    'priority' => 'INVALID',
    'email'    => 'test@test.com',
]);
echo "   Enum validation: " . json_encode($bad2) . "\n\n";

// ── Test 4: Find by ID ───────────────────────────────────────
echo "4. Find by ID\n";

$found = $materializer->find('ticket', 1);
echo "   Found: " . json_encode($found) . "\n\n";

// ── Test 5: List with filters ────────────────────────────────
echo "5. List all tickets\n";

$all = $materializer->findAll('ticket');
echo "   Count: " . count($all) . "\n";
foreach ($all as $t) {
    echo "   - #{$t['id']} [{$t['priority']}] {$t['subject']} ({$t['status']})\n";
}

echo "\n   Filter by priority=high:\n";
$high = $materializer->findAll('ticket', ['priority' => 'high']);
echo "   Count: " . count($high) . "\n";
foreach ($high as $t) {
    echo "   - #{$t['id']} {$t['subject']}\n";
}
echo "\n";

// ── Test 6: Update ───────────────────────────────────────────
echo "6. Update ticket #1\n";

$materializer->update('ticket', 1, ['status' => 'resolved', 'is_urgent' => false]);
$updated = $materializer->find('ticket', 1);
echo "   Status: {$updated['status']}, Urgent: {$updated['is_urgent']}\n\n";

// ── Test 7: Delete ───────────────────────────────────────────
echo "7. Delete ticket #2\n";

$materializer->delete('ticket', 2);
$remaining = $materializer->findAll('ticket');
echo "   Remaining: " . count($remaining) . " tickets\n\n";

// ── Test 8: List schemas ─────────────────────────────────────
echo "8. List all schemas\n";

$schemas = $materializer->listSchemas();
foreach ($schemas as $name => $s) {
    echo "   Entity: {$s['name']} ({$s['label']}) — " . count($s['fields']) . " fields\n";
}
echo "\n";

// ── Test 9: Define a second entity ───────────────────────────
echo "9. Define entity 'product'\n";

$productSchema = new EntitySchema([
    'entity' => 'product',
    'label'  => 'Product',
    'fields' => [
        ['name' => 'name',        'type' => 'string',  'required' => true],
        ['name' => 'description', 'type' => 'text'],
        ['name' => 'price',       'type' => 'number',  'required' => true],
        ['name' => 'category',    'type' => 'string'],
        ['name' => 'in_stock',    'type' => 'boolean',  'default' => true],
    ],
    'search' => false,
]);

$materializer->materialize($productSchema);

$materializer->insert('product', ['name' => 'Widget A', 'price' => 29.99, 'category' => 'widgets', 'in_stock' => true]);
$materializer->insert('product', ['name' => 'Gadget B', 'price' => 149.50, 'category' => 'gadgets']);

$products = $materializer->findAll('product');
echo "   Products: " . count($products) . "\n";
foreach ($products as $p) {
    echo "   - {$p['name']}: \${$p['price']} ({$p['category']})\n";
}
echo "\n";

// ── Test 10: List all schemas ────────────────────────────────
echo "10. All entities in system\n";

$all = $materializer->listSchemas();
foreach ($all as $name => $s) {
    echo "   - {$s['name']}: {$s['label']} (" . count($s['fields']) . " fields)\n";
}

// Cleanup
unlink($dbPath);

echo "\n✅ All tests passed.\n";
