<?php
/**
 * NoWP Cron Runner
 *
 * Execute scheduled workflows. Add to system crontab:
 *   * * * * * php /path/to/nowp/cli/cron.php >> /path/to/nowp/storage/logs/cron.log 2>&1
 *
 * Or run manually:
 *   php cli/cron.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Manual autoload
spl_autoload_register(function ($class) {
    $prefix = 'Framework\\';
    $baseDir = BASE_PATH . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

// Try composer autoload if available
$autoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

use Framework\Agent\Workflow\WorkflowEngine;
use Framework\Agent\Workflow\Scheduler;

// Load config
$config = [];
$configFile = BASE_PATH . '/config/agent.php';
if (file_exists($configFile)) {
    $config = require $configFile;
}

$schedulerPath = $config['scheduler_path'] ?? BASE_PATH . '/storage/agent/schedules';
$engine        = new WorkflowEngine();
$scheduler     = new Scheduler($engine, $schedulerPath);

// Tick
$results = $scheduler->tick();

if (!empty($results)) {
    $timestamp = date('Y-m-d H:i:s');
    foreach ($results as $r) {
        $status = $r['success'] ? 'OK' : 'FAIL';
        echo "[{$timestamp}] [{$status}] {$r['name']} — {$r['steps_run']} steps in {$r['duration_ms']}ms\n";
    }
} else {
    // Uncomment for verbose logging:
    // echo "[" . date('Y-m-d H:i:s') . "] No schedules due.\n";
}
