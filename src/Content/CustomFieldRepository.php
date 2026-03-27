<?php

/**
 * Custom Field Repository
 * 
 * Manages custom fields for content with type validation.
 * Supports string, number, boolean, and date field types.
 * 
 * Requirements: 2.5
 */

declare(strict_types=1);

namespace ChimeraNoWP\Content;

use ChimeraNoWP\Database\Connection;
use ChimeraNoWP\Database\QueryBuilder;

class CustomFieldRepository
{
    private Connection $connection;
    
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Create a new query builder instance
     *
     * @return QueryBuilder
     */
    private function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }
    
    /**
     * Get all custom fields for a content item
     *
     * @param int $contentId Content ID
     * @return array Associative array of field_key => field_value
     */
    public function getFieldsForContent(int $contentId): array
    {
        $rows = $this->newQuery()
            ->table('custom_fields')
            ->where('content_id', $contentId)
            ->get();
        
        $fields = [];
        foreach ($rows as $row) {
            $fields[$row['field_key']] = $this->castValue($row['field_value'], $row['field_type']);
        }
        
        return $fields;
    }
    
    /**
     * Get custom fields for multiple content items (bulk loading to avoid N+1)
     *
     * @param array $contentIds Array of content IDs
     * @return array Map of content_id => [field_key => field_value]
     */
    public function getFieldsForMultipleContents(array $contentIds): array
    {
        if (empty($contentIds)) {
            return [];
        }
        
        // Build WHERE IN clause manually since QueryBuilder doesn't support it yet
        $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
        $query = "SELECT * FROM custom_fields WHERE content_id IN ({$placeholders})";
        
        $rows = $this->connection->fetchAll($query, $contentIds);
        
        // Group by content_id
        $fieldsMap = [];
        foreach ($rows as $row) {
            $contentId = (int) $row['content_id'];
            
            if (!isset($fieldsMap[$contentId])) {
                $fieldsMap[$contentId] = [];
            }
            
            $fieldsMap[$contentId][$row['field_key']] = $this->castValue(
                $row['field_value'],
                $row['field_type']
            );
        }
        
        return $fieldsMap;
    }
    
    /**
     * Get a specific custom field value
     *
     * @param int $contentId Content ID
     * @param string $key Field key
     * @return mixed|null Field value or null if not found
     */
    public function getField(int $contentId, string $key): mixed
    {
        $row = $this->newQuery()
            ->table('custom_fields')
            ->where('content_id', $contentId)
            ->where('field_key', $key)
            ->first();
        
        if (!$row) {
            return null;
        }
        
        return $this->castValue($row['field_value'], $row['field_type']);
    }
    
    /**
     * Set a custom field value with type validation
     *
     * @param int $contentId Content ID
     * @param string $key Field key
     * @param mixed $value Field value
     * @param string $type Field type (string, number, boolean, date)
     * @return void
     * @throws \InvalidArgumentException If value doesn't match type
     */
    public function setField(int $contentId, string $key, mixed $value, string $type = 'string'): void
    {
        // Validate type
        $this->validateFieldType($type);
        
        // Validate value matches type
        $this->validateValue($value, $type);
        
        // Convert value to string for storage
        $storedValue = $this->serializeValue($value, $type);
        
        // Check if field exists
        $existing = $this->newQuery()
            ->table('custom_fields')
            ->where('content_id', $contentId)
            ->where('field_key', $key)
            ->first();
        
        if ($existing) {
            // Update existing field
            $this->newQuery()
                ->table('custom_fields')
                ->where('content_id', $contentId)
                ->where('field_key', $key)
                ->update([
                    'field_value' => $storedValue,
                    'field_type' => $type
                ]);
        } else {
            // Insert new field
            $this->newQuery()
                ->table('custom_fields')
                ->insert([
                    'content_id' => $contentId,
                    'field_key' => $key,
                    'field_value' => $storedValue,
                    'field_type' => $type
                ]);
        }
    }
    
    /**
     * Set multiple custom fields at once
     *
     * @param int $contentId Content ID
     * @param array $fields Associative array of field_key => ['value' => mixed, 'type' => string]
     * @return void
     */
    public function setFields(int $contentId, array $fields): void
    {
        foreach ($fields as $key => $fieldData) {
            $value = $fieldData['value'] ?? $fieldData;
            $type = $fieldData['type'] ?? 'string';
            
            $this->setField($contentId, $key, $value, $type);
        }
    }
    
    /**
     * Delete a custom field
     *
     * @param int $contentId Content ID
     * @param string $key Field key
     * @return bool True if field was deleted
     */
    public function deleteField(int $contentId, string $key): bool
    {
        $affected = $this->newQuery()
            ->table('custom_fields')
            ->where('content_id', $contentId)
            ->where('field_key', $key)
            ->delete();
        
        return $affected > 0;
    }
    
    /**
     * Delete all custom fields for a content item
     *
     * @param int $contentId Content ID
     * @return int Number of fields deleted
     */
    public function deleteAllFields(int $contentId): int
    {
        return $this->newQuery()
            ->table('custom_fields')
            ->where('content_id', $contentId)
            ->delete();
    }
    
    /**
     * Validate field type is supported
     *
     * @param string $type Field type
     * @return void
     * @throws \InvalidArgumentException If type is not supported
     */
    private function validateFieldType(string $type): void
    {
        $validTypes = ['string', 'number', 'boolean', 'date'];
        
        if (!in_array($type, $validTypes)) {
            throw new \InvalidArgumentException(
                "Invalid field type '{$type}'. Must be one of: " . implode(', ', $validTypes)
            );
        }
    }
    
    /**
     * Validate value matches the specified type
     *
     * @param mixed $value Value to validate
     * @param string $type Expected type
     * @return void
     * @throws \InvalidArgumentException If value doesn't match type
     */
    private function validateValue(mixed $value, string $type): void
    {
        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    throw new \InvalidArgumentException("Value must be a string for type 'string'");
                }
                break;
                
            case 'number':
                if (!is_numeric($value)) {
                    throw new \InvalidArgumentException("Value must be numeric for type 'number'");
                }
                break;
                
            case 'boolean':
                if (!is_bool($value)) {
                    throw new \InvalidArgumentException("Value must be a boolean for type 'boolean'");
                }
                break;
                
            case 'date':
                if (!($value instanceof \DateTime) && !$this->isValidDateString($value)) {
                    throw new \InvalidArgumentException("Value must be a DateTime object or valid date string for type 'date'");
                }
                break;
        }
    }
    
    /**
     * Check if string is a valid date
     *
     * @param mixed $value Value to check
     * @return bool
     */
    private function isValidDateString(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        
        try {
            new \DateTime($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Serialize value for storage
     *
     * @param mixed $value Value to serialize
     * @param string $type Field type
     * @return string Serialized value
     */
    private function serializeValue(mixed $value, string $type): string
    {
        switch ($type) {
            case 'string':
                return (string) $value;
                
            case 'number':
                return (string) $value;
                
            case 'boolean':
                return $value ? '1' : '0';
                
            case 'date':
                if ($value instanceof \DateTime) {
                    return $value->format('Y-m-d H:i:s');
                }
                return (new \DateTime($value))->format('Y-m-d H:i:s');
                
            default:
                return (string) $value;
        }
    }
    
    /**
     * Cast stored value to appropriate type
     *
     * @param string $value Stored value
     * @param string $type Field type
     * @return mixed Casted value
     */
    private function castValue(string $value, string $type): mixed
    {
        switch ($type) {
            case 'string':
                return $value;
                
            case 'number':
                return strpos($value, '.') !== false ? (float) $value : (int) $value;
                
            case 'boolean':
                return $value === '1' || $value === 'true';
                
            case 'date':
                return new \DateTime($value);
                
            default:
                return $value;
        }
    }
}
