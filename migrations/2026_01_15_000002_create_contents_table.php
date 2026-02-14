<?php

/**
 * Create Contents Table Migration
 * 
 * Creates the contents table for storing posts, pages, and custom content types.
 * Includes foreign key relationship to users table.
 * 
 * Requirements: 2.1, 2.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Database/Migration.php';

use Framework\Database\Migration;

class CreateContentsTable extends Migration
{
    /**
     * Run the migration
     *
     * @return void
     */
    public function up(): void
    {
        $sql = "
            CREATE TABLE contents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                content LONGTEXT,
                type VARCHAR(50) NOT NULL,
                status VARCHAR(50) NOT NULL,
                author_id INT NOT NULL,
                parent_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                published_at TIMESTAMP NULL,
                INDEX idx_slug (slug),
                INDEX idx_type_status (type, status),
                INDEX idx_author (author_id),
                FOREIGN KEY (author_id) REFERENCES users(id)
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
        $this->connection->execute("DROP TABLE IF EXISTS contents");
    }
}
