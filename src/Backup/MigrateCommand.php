<?php

/**
 * Migrate Command
 * 
 * Migrates URLs when moving site to a new domain.
 * Updates all URL references in database and configuration.
 * 
 * Requirements: 15.5
 */

declare(strict_types=1);

namespace Framework\Backup;

use Framework\Database\Connection;

class MigrateCommand
{
    private Connection $connection;
    
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Migrate URLs from old to new base URL
     *
     * @param string $oldUrl Old base URL (e.g., https://old-site.com)
     * @param string $newUrl New base URL (e.g., https://new-site.com)
     * @param array $options Migration options
     * @return array Statistics about the migration
     */
    public function execute(string $oldUrl, string $newUrl, array $options = []): array
    {
        // Normalize URLs (remove trailing slashes)
        $oldUrl = rtrim($oldUrl, '/');
        $newUrl = rtrim($newUrl, '/');
        
        $stats = [
            'contents_updated' => 0,
            'custom_fields_updated' => 0,
            'media_updated' => 0,
        ];
        
        $this->connection->beginTransaction();
        
        try {
            // Update content URLs
            if ($options['update_contents'] ?? true) {
                $stats['contents_updated'] = $this->updateContentUrls($oldUrl, $newUrl);
            }
            
            // Update custom fields URLs
            if ($options['update_custom_fields'] ?? true) {
                $stats['custom_fields_updated'] = $this->updateCustomFieldUrls($oldUrl, $newUrl);
            }
            
            // Update media URLs
            if ($options['update_media'] ?? true) {
                $stats['media_updated'] = $this->updateMediaUrls($oldUrl, $newUrl);
            }
            
            $this->connection->commit();
            
            return $stats;
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }
    
    /**
     * Update URLs in content table
     *
     * @param string $oldUrl
     * @param string $newUrl
     * @return int Number of rows updated
     */
    private function updateContentUrls(string $oldUrl, string $newUrl): int
    {
        $query = "
            UPDATE contents 
            SET content = REPLACE(content, :old_url, :new_url)
            WHERE content LIKE :search_pattern
        ";
        
        return $this->connection->execute($query, [
            'old_url' => $oldUrl,
            'new_url' => $newUrl,
            'search_pattern' => '%' . $oldUrl . '%',
        ]);
    }
    
    /**
     * Update URLs in custom_fields table
     *
     * @param string $oldUrl
     * @param string $newUrl
     * @return int Number of rows updated
     */
    private function updateCustomFieldUrls(string $oldUrl, string $newUrl): int
    {
        $query = "
            UPDATE custom_fields 
            SET field_value = REPLACE(field_value, :old_url, :new_url)
            WHERE field_value LIKE :search_pattern
        ";
        
        return $this->connection->execute($query, [
            'old_url' => $oldUrl,
            'new_url' => $newUrl,
            'search_pattern' => '%' . $oldUrl . '%',
        ]);
    }
    
    /**
     * Update URLs in media table
     *
     * @param string $oldUrl
     * @param string $newUrl
     * @return int Number of rows updated
     */
    private function updateMediaUrls(string $oldUrl, string $newUrl): int
    {
        $query = "
            UPDATE media 
            SET path = REPLACE(path, :old_url, :new_url)
            WHERE path LIKE :search_pattern
        ";
        
        return $this->connection->execute($query, [
            'old_url' => $oldUrl,
            'new_url' => $newUrl,
            'search_pattern' => '%' . $oldUrl . '%',
        ]);
    }
}
