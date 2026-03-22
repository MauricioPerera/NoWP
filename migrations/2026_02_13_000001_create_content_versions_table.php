<?php

/**
 * Create Content Versions Table Migration
 * 
 * Creates the content_versions table for storing content version history.
 * Supports content versioning requirement.
 * 
 * Requirements: 2.4
 */

declare(strict_types=1);

use Framework\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->connection->execute("
            CREATE TABLE IF NOT EXISTS content_versions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                content_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                content LONGTEXT,
                type VARCHAR(50) NOT NULL,
                status VARCHAR(50) NOT NULL,
                author_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_content_id (content_id),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE IF EXISTS content_versions");
    }
};
