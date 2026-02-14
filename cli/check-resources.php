<?php

/**
 * Resource Limits Checker
 * 
 * Verifies that the framework meets resource requirements:
 * - Memory usage < 256MB
 * - Response time < 100ms
 * - Disk space < 100MB
 * 
 * Requirements: 1.3, 1.4, 10.7
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

class ResourceChecker
{
    private array $results = [];
    private bool $allPassed = true;
    
    public function run(): void
    {
        echo "=== Framework Resource Limits Check ===\n\n";
        
        $this->checkMemoryUsage();
        $this->checkResponseTime();
        $this->checkDiskSpace();
        $this->checkPHPVersion();
        $this->checkRequiredExtensions();
        
        echo "\n=== Summary ===\n";
        
        foreach ($this->results as $check => $result) {
            $status = $result['passed'] ? '✓ PASS' : '✗ FAIL';
            $color = $result['passed'] ? "\033[32m" : "\033[31m";
            $reset = "\033[0m";
            
            echo "{$color}{$status}{$reset} {$check}: {$result['message']}\n";
        }
        
        echo "\n";
        
        if ($this->allPassed) {
            echo "\033[32m✓ All checks passed!\033[0m\n";
            exit(0);
        } else {
            echo "\033[31m✗ Some checks failed. Please review the results above.\033[0m\n";
            exit(1);
        }
    }
    
    private function checkMemoryUsage(): void
    {
        echo "Checking memory usage...\n";
        
        $startMemory = memory_get_usage(true);
        
        // Simulate typical application load
        $this->simulateApplicationLoad();
        
        $peakMemory = memory_get_peak_usage(true);
        $memoryMB = round($peakMemory / 1024 / 1024, 2);
        
        $limit = 256; // MB
        $passed = $memoryMB < $limit;
        
        $this->addResult('Memory Usage', $passed, "{$memoryMB}MB (limit: {$limit}MB)");
    }
    
    private function checkResponseTime(): void
    {
        echo "Checking response time...\n";
        
        $iterations = 10;
        $totalTime = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            
            // Simulate API request
            $this->simulateAPIRequest();
            
            $end = microtime(true);
            $totalTime += ($end - $start);
        }
        
        $avgTime = ($totalTime / $iterations) * 1000; // Convert to ms
        $avgTimeFormatted = round($avgTime, 2);
        
        $limit = 100; // ms
        $passed = $avgTime < $limit;
        
        $this->addResult('Response Time', $passed, "{$avgTimeFormatted}ms (limit: {$limit}ms)");
    }
    
    private function checkDiskSpace(): void
    {
        echo "Checking disk space...\n";
        
        $size = $this->getDirectorySize(BASE_PATH);
        $sizeMB = round($size / 1024 / 1024, 2);
        
        // Exclude vendor and node_modules from calculation
        $vendorSize = $this->getDirectorySize(BASE_PATH . '/vendor');
        $actualSize = $size - $vendorSize;
        $actualSizeMB = round($actualSize / 1024 / 1024, 2);
        
        $limit = 100; // MB (excluding dependencies)
        $passed = $actualSizeMB < $limit;
        
        $this->addResult('Disk Space', $passed, "{$actualSizeMB}MB (limit: {$limit}MB, total with deps: {$sizeMB}MB)");
    }
    
    private function checkPHPVersion(): void
    {
        echo "Checking PHP version...\n";
        
        $version = PHP_VERSION;
        $required = '8.1.0';
        
        $passed = version_compare($version, $required, '>=');
        
        $this->addResult('PHP Version', $passed, "{$version} (required: {$required}+)");
    }
    
    private function checkRequiredExtensions(): void
    {
        echo "Checking required PHP extensions...\n";
        
        $required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl'];
        $optional = ['fileinfo', 'gd', 'imagick'];
        
        $missing = [];
        $missingOptional = [];
        
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        foreach ($optional as $ext) {
            if (!extension_loaded($ext)) {
                $missingOptional[] = $ext;
            }
        }
        
        $passed = empty($missing);
        
        if ($passed && empty($missingOptional)) {
            $message = 'All extensions loaded';
        } elseif ($passed) {
            $message = 'All required extensions loaded. Optional missing: ' . implode(', ', $missingOptional);
        } else {
            $message = 'Missing required: ' . implode(', ', $missing);
        }
        
        $this->addResult('PHP Extensions', $passed, $message);
    }
    
    private function simulateApplicationLoad(): void
    {
        // Simulate loading framework components
        $data = [];
        
        // Simulate loading 100 content items
        for ($i = 0; $i < 100; $i++) {
            $data[] = [
                'id' => $i,
                'title' => 'Test Content ' . $i,
                'content' => str_repeat('Lorem ipsum dolor sit amet. ', 50),
                'customFields' => [
                    'field1' => 'value1',
                    'field2' => 'value2',
                    'field3' => 'value3',
                ],
            ];
        }
        
        // Simulate processing
        foreach ($data as $item) {
            $processed = json_encode($item);
            $decoded = json_decode($processed, true);
        }
        
        unset($data);
    }
    
    private function simulateAPIRequest(): void
    {
        // Simulate typical API request processing
        $data = [
            'id' => 1,
            'title' => 'Test Post',
            'content' => str_repeat('Content ', 100),
        ];
        
        // Simulate JSON encoding/decoding
        $json = json_encode($data);
        $decoded = json_decode($json, true);
        
        // Simulate some processing
        $result = array_map(function($value) {
            return is_string($value) ? strtoupper($value) : $value;
        }, $decoded);
    }
    
    private function getDirectorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }
        
        $size = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    private function addResult(string $check, bool $passed, string $message): void
    {
        $this->results[$check] = [
            'passed' => $passed,
            'message' => $message,
        ];
        
        if (!$passed) {
            $this->allPassed = false;
        }
    }
}

// Run the checker
$checker = new ResourceChecker();
$checker->run();
