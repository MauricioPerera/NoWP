<?php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

// Load env
foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#')) continue;
    if (str_contains($l, '=')) {
        [$k, $v] = explode('=', $l, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        putenv(trim($k) . '=' . trim($v, " \t\n\r\0\x0B\"'"));
    }
}

$config = require BASE_PATH . '/config/app.php';
$app = new Framework\Core\Application($config);

try {
    $app->boot();
} catch (Throwable $e) {
    echo "BOOT ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

$ref = new ReflectionProperty($app, 'container');
$ref->setAccessible(true);
$c = $ref->getValue($app);

echo "Has Connection: " . ($c->has(Framework\Database\Connection::class) ? 'YES' : 'NO') . "\n";
echo "Has EntityMaterializer: " . ($c->has(Framework\Agent\Data\EntityMaterializer::class) ? 'YES' : 'NO') . "\n";
echo "Has AgentService: " . ($c->has(Framework\Agent\AgentService::class) ? 'YES' : 'NO') . "\n";
echo "Has IntegrationManager: " . ($c->has(Framework\Agent\Integration\IntegrationManager::class) ? 'YES' : 'NO') . "\n";
echo "Has Scheduler: " . ($c->has(Framework\Agent\Workflow\Scheduler::class) ? 'YES' : 'NO') . "\n";
echo "Has PageBuilder: " . ($c->has(Framework\Agent\Page\PageBuilder::class) ? 'YES' : 'NO') . "\n";
echo "Has TestRunner: " . ($c->has(Framework\Agent\Testing\TestRunner::class) ? 'YES' : 'NO') . "\n";

// Count tools
if ($c->has(Framework\Agent\AgentService::class)) {
    $agent = $c->get(Framework\Agent\AgentService::class);
    $ref2 = new ReflectionProperty($agent, 'tools');
    $ref2->setAccessible(true);
    $tools = $ref2->getValue($agent);
    echo "Tools count: " . count($tools) . "\n";
    foreach ($tools as $t) {
        echo "  - " . $t->name() . "\n";
    }
}
