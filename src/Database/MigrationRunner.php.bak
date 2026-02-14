<?php

/**
 * Base Migration Class
 * 
 * Abstract base class for database migrations. Each migration must implement
 * up() and down() methods to define schema changes and their rollback.
 * 
 * Requirements: 5.3
 */

declare(strict_types=1);

namespace Framework\Database;

abstract class Migration
{
    protected Connection $connection;
    
    /**
     * Create a new migration instance
     *
     * @param Connection $connection Database connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Run the migration (apply schema changes)
     *
     * @return void
     */
    abstract public function up(): void;
    
    /**
     * Reverse the migration (rollback schema changes)
     *
     * @return void
     */
    abstract public function down(): void;
    
    /**
     * Get the migration name
     * 
     * By default, returns the class name without namespace
     *
     * @return string
     */
    public function getName(): string
    {
        $className = get_class($this);
        return substr($className, strrpos($className, '\\') + 1);
    }
}
