<?php

/**
 * Fluent Query Builder
 * 
 * Provides a fluent interface for building SQL queries with automatic
 * prepared statement handling for security. Supports CRUD operations.
 * 
 * Requirements: 5.1, 5.2, 5.4
 */

declare(strict_types=1);

namespace Framework\Database;

use PDO;

class QueryBuilder
{
    private Connection $connection;
    private string $table = '';
    private array $columns = ['*'];
    private array $wheres = [];
    private array $joins = [];
    private array $orderBys = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $bindings = [];
    
    /**
     * Create a new query builder instance
     *
     * @param Connection $connection Database connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    
    /**
     * Set the table for the query
     *
     * @param string $table Table name
     * @return self
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }
    
    /**
     * Set the columns to select
     *
     * @param array|string $columns Columns to select
     * @return self
     */
    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }
    
    /**
     * Add a WHERE clause
     *
     * @param string $column Column name
     * @param mixed $value Value to compare
     * @param string $operator Comparison operator
     * @return self
     */
    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];
        
        return $this;
    }
    
    /**
     * Add an OR WHERE clause
     *
     * @param string $column Column name
     * @param mixed $value Value to compare
     * @param string $operator Comparison operator
     * @return self
     */
    public function orWhere(string $column, mixed $value, string $operator = '='): self
    {
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];
        
        return $this;
    }
    
    /**
     * Add a WHERE IN clause
     *
     * @param string $column Column name
     * @param array $values Values to check
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];
        
        return $this;
    }
    
    /**
     * Add a WHERE NULL clause
     *
     * @param string $column Column name
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND'
        ];
        
        return $this;
    }
    
    /**
     * Add a WHERE NOT NULL clause
     *
     * @param string $column Column name
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'AND'
        ];
        
        return $this;
    }
    
    /**
     * Add a JOIN clause
     *
     * @param string $table Table to join
     * @param string $first First column
     * @param string $second Second column
     * @param string $type Join type (INNER, LEFT, RIGHT)
     * @return self
     */
    public function join(string $table, string $first, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $table,
            'first' => $first,
            'second' => $second
        ];
        
        return $this;
    }
    
    /**
     * Add a LEFT JOIN clause
     *
     * @param string $table Table to join
     * @param string $first First column
     * @param string $second Second column
     * @return self
     */
    public function leftJoin(string $table, string $first, string $second): self
    {
        return $this->join($table, $first, $second, 'LEFT');
    }
    
    /**
     * Add a RIGHT JOIN clause
     *
     * @param string $table Table to join
     * @param string $first First column
     * @param string $second Second column
     * @return self
     */
    public function rightJoin(string $table, string $first, string $second): self
    {
        return $this->join($table, $first, $second, 'RIGHT');
    }
    
    /**
     * Add an ORDER BY clause
     *
     * @param string $column Column to order by
     * @param string $direction Sort direction (asc or desc)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderBys[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        
        return $this;
    }
    
    /**
     * Set the LIMIT clause
     *
     * @param int $limit Number of rows to limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }
    
    /**
     * Set the OFFSET clause
     *
     * @param int $offset Number of rows to skip
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }
    
    /**
     * Execute the query and get all results
     *
     * @return array
     */
    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        return $this->connection->fetchAll($sql, $this->bindings);
    }
    
    /**
     * Execute the query and get the first result
     *
     * @return array|null
     */
    public function first(): ?array
    {
        $this->limit(1);
        $sql = $this->buildSelectQuery();
        $result = $this->connection->fetchOne($sql, $this->bindings);
        
        return $result === false ? null : $result;
    }
    
    /**
     * Get the count of rows
     *
     * @return int
     */
    public function count(): int
    {
        $originalColumns = $this->columns;
        $this->columns = ['COUNT(*) as count'];
        
        $sql = $this->buildSelectQuery();
        $result = $this->connection->fetchOne($sql, $this->bindings);
        
        $this->columns = $originalColumns;
        
        return (int) ($result['count'] ?? 0);
    }
    
    /**
     * Insert a new record
     *
     * @param array $data Associative array of column => value
     * @return int Last inserted ID
     */
    public function insert(array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Insert data cannot be empty');
        }
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $this->connection->execute($sql, array_values($data));
        
        return (int) $this->connection->lastInsertId();
    }
    
    /**
     * Update existing records
     *
     * @param array $data Associative array of column => value
     * @return int Number of affected rows
     */
    public function update(array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Update data cannot be empty');
        }
        
        $sets = [];
        $updateBindings = [];
        
        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $updateBindings[] = $value;
        }
        
        // Build WHERE clause (this populates $this->bindings)
        $whereClause = $this->buildWhereClause();
        
        // Merge update bindings with WHERE bindings
        $allBindings = array_merge($updateBindings, $this->bindings);
        
        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->table,
            implode(', ', $sets),
            $whereClause
        );
        
        return $this->connection->execute($sql, $allBindings);
    }
    
    /**
     * Delete records
     *
     * @return int Number of affected rows
     */
    public function delete(): int
    {
        // Build WHERE clause (this populates $this->bindings)
        $whereClause = $this->buildWhereClause();
        
        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->table,
            $whereClause
        );
        
        return $this->connection->execute($sql, $this->bindings);
    }
    
    /**
     * Build the SELECT query
     *
     * @return string
     */
    private function buildSelectQuery(): string
    {
        $parts = [];
        
        // SELECT
        $parts[] = 'SELECT ' . implode(', ', $this->columns);
        
        // FROM
        $parts[] = 'FROM ' . $this->table;
        
        // JOINs
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $parts[] = sprintf(
                    '%s JOIN %s ON %s = %s',
                    $join['type'],
                    $join['table'],
                    $join['first'],
                    $join['second']
                );
            }
        }
        
        // WHERE
        $whereClause = $this->buildWhereClause();
        if ($whereClause) {
            $parts[] = $whereClause;
        }
        
        // ORDER BY
        if (!empty($this->orderBys)) {
            $orderParts = [];
            foreach ($this->orderBys as $order) {
                $orderParts[] = "{$order['column']} {$order['direction']}";
            }
            $parts[] = 'ORDER BY ' . implode(', ', $orderParts);
        }
        
        // LIMIT
        if ($this->limitValue !== null) {
            $parts[] = 'LIMIT ' . $this->limitValue;
        }
        
        // OFFSET
        if ($this->offsetValue !== null) {
            $parts[] = 'OFFSET ' . $this->offsetValue;
        }
        
        return implode(' ', $parts);
    }
    
    /**
     * Build the WHERE clause
     *
     * @return string
     */
    private function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        
        $this->bindings = [];
        $conditions = [];
        
        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? '' : " {$where['boolean']} ";
            
            switch ($where['type']) {
                case 'basic':
                    $conditions[] = $boolean . "{$where['column']} {$where['operator']} ?";
                    $this->bindings[] = $where['value'];
                    break;
                    
                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $conditions[] = $boolean . "{$where['column']} IN ({$placeholders})";
                    $this->bindings = array_merge($this->bindings, $where['values']);
                    break;
                    
                case 'null':
                    $conditions[] = $boolean . "{$where['column']} IS NULL";
                    break;
                    
                case 'not_null':
                    $conditions[] = $boolean . "{$where['column']} IS NOT NULL";
                    break;
            }
        }
        
        return ' WHERE ' . implode('', $conditions);
    }
    
    /**
     * Get the raw SQL query (for debugging)
     *
     * @return string
     */
    public function toSql(): string
    {
        return $this->buildSelectQuery();
    }
    
    /**
     * Get the bindings (for debugging)
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
