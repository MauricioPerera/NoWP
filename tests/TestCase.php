<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for all tests
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup test environment
        $this->setUpTestEnvironment();
    }
    
    protected function tearDown(): void
    {
        // Cleanup after tests
        $this->cleanupTestEnvironment();
        
        parent::tearDown();
    }
    
    /**
     * Set up test environment
     */
    protected function setUpTestEnvironment(): void
    {
        // Override in child classes if needed
    }
    
    /**
     * Clean up test environment
     */
    protected function cleanupTestEnvironment(): void
    {
        // Override in child classes if needed
    }
}
