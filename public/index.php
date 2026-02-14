<?php

/**
 * WordPress Alternative Framework
 * Entry point for all requests
 */

declare(strict_types=1);

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

// Load Composer autoloader
require BASE_PATH . '/vendor/autoload.php';

// Load configuration
$config = require BASE_PATH . '/config/app.php';

// Bootstrap and run application
$app = new Framework\Core\Application($config);
$app->boot();

// Handle the request
$request = Framework\Core\Request::createFromGlobals();
$response = $app->handle($request);

// Send response
$response->send();
