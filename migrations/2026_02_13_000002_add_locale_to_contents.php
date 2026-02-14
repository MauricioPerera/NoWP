<?php

/**
 * Migration: Add locale support to contents table
 * 
 * Requirements: 14.1, 14.2
 */

use Framework\Database\Migration;
use Framework\Database\Connection;

return new class extends Migration
{
    public function up(): void
    {
        $this->connection->execute("
            ALTER TABLE contents 
            ADD COLUMN locale VARCHAR(10) DEFAULT 'en' NOT NULL,
            ADD COLUMN translation_group VARCHAR(255) NULL,
            ADD INDEX idx_locale (locale),
            ADD INDEX idx_translation_group (translation_group)
        ");
    }
    
    public function down(): void
    {
        // SQLite doesn't support DROP COLUMN easily, so we recreate the table
        // For MySQL, this would work:
        // ALTER TABLE contents DROP COLUMN locale, DROP COLUMN translation_group
        
        $this->connection->execute("
            CREATE TABLE contents_backup AS SELECT 
                id, title, slug, content, excerpt, type, status, 
                author_id, parent_id, published_at, created_at, updated_at
            FROM contents
        ");
        
        $this->connection->execute("DROP TABLE contents");
        
        $this->connection->execute("
            CREATE TABLE contents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                content TEXT,
                excerpt TEXT,
                type VARCHAR(50) NOT NULL DEFAULT 'post',
                status VARCHAR(50) NOT NULL DEFAULT 'draft',
                author_id INTEGER NOT NULL,
                parent_id INTEGER NULL,
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES contents(id) ON DELETE SET NULL
            )
        ");
        
        $this->connection->execute("
            INSERT INTO contents SELECT * FROM contents_backup
        ");
        
        $this->connection->execute("DROP TABLE contents_backup");
        
        $this->connection->execute("CREATE INDEX idx_slug ON contents(slug)");
        $this->connection->execute("CREATE INDEX idx_type ON contents(type)");
        $this->connection->execute("CREATE INDEX idx_status ON contents(status)");
        $this->connection->execute("CREATE INDEX idx_author_id ON contents(author_id)");
        $this->connection->execute("CREATE INDEX idx_published_at ON contents(published_at)");
    }
};
