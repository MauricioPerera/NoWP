<?php

/**
 * Pest PHP Configuration
 */

declare(strict_types=1);

// Define base path for tests
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');

// Load Composer autoloader
require BASE_PATH . '/vendor/autoload.php';

// Load test helpers
require __DIR__ . '/Helpers.php';

// Set default test namespace
uses(Tests\TestCase::class)->in('Unit', 'Properties', 'Integration', 'Feature');

// Helper functions for tests
function createTestDatabase(): void
{
    // Helper to create test database
}

function cleanupTestDatabase(): void
{
    // Helper to cleanup test database
}
