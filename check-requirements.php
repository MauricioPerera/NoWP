<?php

/**
 * System Requirements Checker
 * Run this script to verify your server meets the framework requirements
 */

declare(strict_types=1);

echo "WordPress Alternative Framework - Requirements Checker\n";
echo str_repeat("=", 60) . "\n\n";

$requirements = [
    'PHP Version' => [
        'required' => '8.1.0',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '8.1.0', '>='),
    ],
    'PDO Extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('pdo') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('pdo'),
    ],
    'PDO MySQL Driver' => [
        'required' => 'Enabled',
        'current' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('pdo_mysql'),
    ],
    'JSON Extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('json'),
    ],
    'mbstring Extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('mbstring') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('mbstring'),
    ],
    'GD Extension' => [
        'required' => 'Enabled (for image processing)',
        'current' => extension_loaded('gd') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('gd'),
    ],
];

$allPassed = true;

foreach ($requirements as $name => $check) {
    $status = $check['status'] ? '✓ PASS' : '✗ FAIL';
    $color = $check['status'] ? "\033[32m" : "\033[31m";
    $reset = "\033[0m";
    
    echo sprintf(
        "%-25s %s%-10s%s Required: %s, Current: %s\n",
        $name . ':',
        $color,
        $status,
        $reset,
        $check['required'],
        $check['current']
    );
    
    if (!$check['status']) {
        $allPassed = false;
    }
}

echo "\n" . str_repeat("=", 60) . "\n";

if ($allPassed) {
    echo "\033[32m✓ All requirements met! You can proceed with installation.\033[0m\n";
    exit(0);
} else {
    echo "\033[31m✗ Some requirements are not met. Please install missing extensions.\033[0m\n";
    exit(1);
}
