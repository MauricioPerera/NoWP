<?php

/**
 * Create Custom Fields Table Migration
 * 
 * Creates the custom_fields table for storing custom metadata associated with content.
 * Includes foreign key relationship to contents table with cascade delete.
 * 
 * Requirements: 2.1, 2.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Database/Migration.php';

use Framework\Database\Migration;

class CreateCustomFieldsTable extends Migration
{
    /**
     * Run the migration
     *
     * @return void
     */
    public function up(): void
    {
        $sql = "
            CREATE TABLE custom_fields (
                id INT PRIMARY KEY AUTO_INCREMENT,
                content_id INT NOT NULL,
                field_key VARCHAR(255) NOT NULL,
                field_value LONGTEXT,
                field_type VARCHAR(50) NOT NULL,
                UNIQUE KEY unique_content_field (content_id, field_key),
                FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->connection->execute($sql);
    }
    
    /**
     * Reverse the migration
     *
     * @return void
     */
    public function down(): void
    {
        $this->connection->execute("DROP TABLE IF EXISTS custom_fields");
    }
}
