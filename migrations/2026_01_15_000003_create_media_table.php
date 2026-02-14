<?php

/**
 * Create Media Table Migration
 * 
 * Creates the media table for storing uploaded files and images.
 * Includes foreign key relationship to users table.
 * 
 * Requirements: 2.1, 2.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Database/Migration.php';

use Framework\Database\Migration;

class CreateMediaTable extends Migration
{
    /**
     * Run the migration
     *
     * @return void
     */
    public function up(): void
    {
        $sql = "
            CREATE TABLE media (
                id INT PRIMARY KEY AUTO_INCREMENT,
                filename VARCHAR(255) NOT NULL,
                path VARCHAR(500) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                size INT NOT NULL,
                width INT NULL,
                height INT NULL,
                uploaded_by INT NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_uploaded_by (uploaded_by),
                FOREIGN KEY (uploaded_by) REFERENCES users(id)
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
        $this->connection->execute("DROP TABLE IF EXISTS media");
    }
}
