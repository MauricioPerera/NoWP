<?php

/**
 * Create Users Table Migration
 * 
 * Creates the users table for storing user accounts with authentication
 * and role information.
 * 
 * Requirements: 2.1, 2.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Database/Migration.php';

use ChimeraNoWP\Database\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migration
     *
     * @return void
     */
    public function up(): void
    {
        $sql = "
            CREATE TABLE users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login_at TIMESTAMP NULL,
                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_created_at (created_at)
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
        $this->connection->execute("DROP TABLE IF EXISTS users");
    }
}
