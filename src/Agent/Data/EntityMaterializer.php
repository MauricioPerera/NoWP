<?php

/**
 * A2D Entity Materializer — creates storage, CRUD, API, and search from schema.
 *
 * Takes an EntitySchema and materializes:
 * 1. Database table (CREATE TABLE)
 * 2. CRUD operations (insert, find, findAll, update, delete)
 * 3. Validation (from schema rules)
 * 4. Search index (auto-vectorize on save if searchable)
 * 5. REST API routes (if apiEnabled)
 */

declare(strict_types=1);

namespace Framework\Agent\Data;

use Framework\Database\Connection;
use Framework\Search\SearchService;

class EntityMaterializer
{
    private Connection $db;
    private ?SearchService $search;

    /** @var array<string, EntitySchema> */
    private array $schemas = [];

    public function __construct(Connection $db, ?SearchService $search = null)
    {
        $this->db     = $db;
        $this->search = $search;
    }

    /**
     * Materialize an entity from a declarative schema.
     * Creates the table if it doesn't exist and registers the schema.
     */
    public function materialize(EntitySchema $schema): array
    {
        // 1. Create table
        $driver = $this->db->getDriver();
        $sql    = $schema->toCreateSQL($driver);
        $this->db->exec($sql);

        // 2. Register schema
        $this->schemas[$schema->name] = $schema;
        $this->persistSchemas();

        return [
            'entity'  => $schema->name,
            'table'   => $schema->tableName(),
            'fields'  => count($schema->fields),
            'search'  => $schema->searchable,
            'api'     => $schema->apiEnabled,
            'status'  => 'materialized',
        ];
    }

    /**
     * Drop an entity — removes table, schema, and search index.
     */
    public function dematerialize(string $name): array
    {
        $schema = $this->getSchema($name);
        if (!$schema) {
            return ['error' => "Entity '{$name}' not found."];
        }

        $this->db->exec("DROP TABLE IF EXISTS {$schema->tableName()}");

        if ($this->search && $schema->searchable) {
            $this->search->dropCollection("a2d_{$name}");
        }

        unset($this->schemas[$name]);
        $this->persistSchemas();

        return ['entity' => $name, 'status' => 'dematerialized'];
    }

    // ── CRUD ────────────────────────────────────────────────────────

    /**
     * Insert a record.
     */
    public function insert(string $entity, array $data): array
    {
        $schema = $this->getSchema($entity);
        if (!$schema) return ['error' => "Entity '{$entity}' not found."];

        // Validate
        $errors = $this->validate($schema, $data);
        if (!empty($errors)) return ['error' => 'Validation failed.', 'details' => $errors];

        // Filter to known fields only
        $clean = $this->filterFields($schema, $data);
        $clean['created_at'] = date('Y-m-d H:i:s');
        $clean['updated_at'] = $clean['created_at'];

        // Insert
        $cols   = implode(', ', array_keys($clean));
        $places = implode(', ', array_fill(0, count($clean), '?'));
        $this->db->exec(
            "INSERT INTO {$schema->tableName()} ({$cols}) VALUES ({$places})",
            array_values($clean)
        );
        $id = (int) $this->db->lastInsertId();

        // Index for search
        if ($this->search && $schema->searchable) {
            $text = $this->buildSearchText($schema, $clean);
            $this->search->index("a2d_{$entity}", (string) $id, $text, ['entity' => $entity]);
        }

        $clean['id'] = $id;
        return $clean;
    }

    /**
     * Find a record by ID.
     */
    public function find(string $entity, int $id): ?array
    {
        $schema = $this->getSchema($entity);
        if (!$schema) return null;

        $row = $this->db->fetch(
            "SELECT * FROM {$schema->tableName()} WHERE id = ?",
            [$id]
        );

        return $row ?: null;
    }

    /**
     * Find all records with optional filters.
     */
    public function findAll(string $entity, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $schema = $this->getSchema($entity);
        if (!$schema) return [];

        $where  = [];
        $params = [];

        foreach ($filters as $key => $value) {
            $where[]  = "{$key} = ?";
            $params[] = $value;
        }

        $sql = "SELECT * FROM {$schema->tableName()}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Update a record.
     */
    public function update(string $entity, int $id, array $data): array
    {
        $schema = $this->getSchema($entity);
        if (!$schema) return ['error' => "Entity '{$entity}' not found."];

        $clean = $this->filterFields($schema, $data);
        $clean['updated_at'] = date('Y-m-d H:i:s');

        $sets   = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($clean)));
        $params = array_values($clean);
        $params[] = $id;

        $this->db->exec(
            "UPDATE {$schema->tableName()} SET {$sets} WHERE id = ?",
            $params
        );

        // Re-index
        if ($this->search && $schema->searchable) {
            $record = $this->find($entity, $id);
            if ($record) {
                $text = $this->buildSearchText($schema, $record);
                $this->search->index("a2d_{$entity}", (string) $id, $text, ['entity' => $entity]);
            }
        }

        return ['id' => $id, 'updated' => true];
    }

    /**
     * Delete a record.
     */
    public function delete(string $entity, int $id): array
    {
        $schema = $this->getSchema($entity);
        if (!$schema) return ['error' => "Entity '{$entity}' not found."];

        $this->db->exec("DELETE FROM {$schema->tableName()} WHERE id = ?", [$id]);

        if ($this->search && $schema->searchable) {
            $this->search->remove("a2d_{$entity}", (string) $id);
        }

        return ['id' => $id, 'deleted' => true];
    }

    /**
     * Semantic search within an entity.
     */
    public function search(string $entity, string $query, int $limit = 10): array
    {
        $schema = $this->getSchema($entity);
        if (!$schema || !$schema->searchable || !$this->search) {
            return [];
        }

        $results = $this->search->search("a2d_{$entity}", $query, $limit);

        // Enrich with full records
        $enriched = [];
        foreach ($results as $r) {
            $record = $this->find($entity, (int) $r['id']);
            if ($record) {
                $record['_score'] = $r['score'];
                $enriched[] = $record;
            }
        }

        return $enriched;
    }

    // ── Schema Management ──────────────────────────────────────────

    public function getSchema(string $name): ?EntitySchema
    {
        if (isset($this->schemas[$name])) {
            return $this->schemas[$name];
        }

        // Load from storage
        $this->loadSchemas();
        return $this->schemas[$name] ?? null;
    }

    public function listSchemas(): array
    {
        $this->loadSchemas();
        return array_map(fn(EntitySchema $s) => $s->toArray(), $this->schemas);
    }

    // ── Private ────────────────────────────────────────────────────

    private function validate(EntitySchema $schema, array $data): array
    {
        $errors = [];
        foreach ($schema->validationRules() as $field => $rules) {
            $value = $data[$field] ?? null;

            if (($rules['required'] ?? false) && (null === $value || '' === $value)) {
                $errors[$field] = "Field '{$field}' is required.";
                continue;
            }

            if (null === $value) continue;

            if (isset($rules['in']) && !in_array($value, $rules['in'], true)) {
                $errors[$field] = "Field '{$field}' must be one of: " . implode(', ', $rules['in']);
            }
        }
        return $errors;
    }

    private function filterFields(EntitySchema $schema, array $data): array
    {
        $known = array_column($schema->fields, 'name');
        return array_intersect_key($data, array_flip($known));
    }

    private function buildSearchText(EntitySchema $schema, array $record): string
    {
        $parts = [];
        foreach ($schema->searchableFields() as $field) {
            $value = $record[$field['name']] ?? '';
            if ('' !== (string) $value) {
                $parts[] = $value;
            }
        }
        return implode("\n", $parts);
    }

    private function persistSchemas(): void
    {
        $data = array_map(fn(EntitySchema $s) => $s->toArray(), $this->schemas);
        // Store in a meta table
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS a2d_schemas (name VARCHAR(100) PRIMARY KEY, definition TEXT)"
        );
        foreach ($data as $name => $def) {
            $json = json_encode($def);
            $this->db->exec(
                "REPLACE INTO a2d_schemas (name, definition) VALUES (?, ?)",
                [$name, $json]
            );
        }
    }

    private function loadSchemas(): void
    {
        if (!empty($this->schemas)) return;

        try {
            $rows = $this->db->fetchAll("SELECT name, definition FROM a2d_schemas");
            foreach ($rows as $row) {
                $def = json_decode($row['definition'], true);
                if ($def) {
                    $this->schemas[$row['name']] = new EntitySchema($def);
                }
            }
        } catch (\Throwable) {
            // Table doesn't exist yet — will be created on first materialize
        }
    }
}
