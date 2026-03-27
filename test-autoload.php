<?php

require 'vendor/autoload.php';

try {
    $class = new \ChimeraNoWP\Database\MigrationRunner(null, '/tmp');
    echo "Class loaded successfully\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
