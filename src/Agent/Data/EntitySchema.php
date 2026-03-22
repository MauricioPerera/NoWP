<?php

/**
 * A2D Entity Schema — declarative data model definition.
 *
 * An agent declares what data it needs, and the system materializes
 * the storage, validation, API, and search index automatically.
 *
 * Supported field types:
 *   string, text, integer, number, boolean, date, datetime,
 *   enum (with values), relation (with target entity), json
 */

declare(strict_types=1);

namespace Framework\Agent\Data;

class EntitySchema
{
    public readonly string $name;
    public readonly string $label;
    public readonly string $description;
    public readonly array $fields;
    public readonly bool $searchable;
    public readonly bool $apiEnabled;
    public readonly string $createdAt;

    public function __construct(array $definition)
    {
        $this->name        = self::sanitizeKey($definition['entity'] ?? $definition['name'] ?? '');
        $this->label       = $definition['label'] ?? ucfirst(str_replace('_', ' ', $this->name));
        $this->description = $definition['description'] ?? '';
        $this->fields      = self::parseFields($definition['fields'] ?? []);
        $this->searchable  = (bool) ($definition['search'] ?? false);
        $this->apiEnabled  = (bool) ($definition['api'] ?? true);
        $this->createdAt   = $definition['created_at'] ?? date('c');
    }

    /**
     * Get the database table name.
     */
    public function tableName(): string
    {
        return 'a2d_' . $this->name;
    }

    /**
     * Generate SQL CREATE TABLE statement.
     */
    public function toCreateSQL(string $driver = 'mysql'): string
    {
        $table   = $this->tableName();
        $columns = ["id INTEGER PRIMARY KEY " . ($driver === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT')];

        foreach ($this->fields as $field) {
            $columns[] = $this->fieldToSQL($field, $driver);
        }

        $columns[] = "created_at DATETIME DEFAULT CURRENT_TIMESTAMP";
        $columns[] = "updated_at DATETIME DEFAULT CURRENT_TIMESTAMP";

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (\n  " . implode(",\n  ", $columns) . "\n)";

        if ($driver === 'mysql') {
            $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        return $sql;
    }

    /**
     * Generate validation rules from schema.
     */
    public function validationRules(): array
    {
        $rules = [];
        foreach ($this->fields as $field) {
            $rule = ['type' => $field['type']];
            if ($field['required'] ?? false) {
                $rule['required'] = true;
            }
            if (isset($field['values'])) {
                $rule['in'] = $field['values'];
            }
            if (isset($field['min'])) {
                $rule['min'] = $field['min'];
            }
            if (isset($field['max'])) {
                $rule['max'] = $field['max'];
            }
            $rules[$field['name']] = $rule;
        }
        return $rules;
    }

    /**
     * Get fields that should be included in vector embedding text.
     */
    public function searchableFields(): array
    {
        return array_filter($this->fields, function ($f) {
            $type = $f['type'];
            // Only text-like fields contribute to embeddings
            return in_array($type, ['string', 'text', 'enum'], true)
                && ($f['searchable'] ?? true);
        });
    }

    /**
     * Serialize to storable array.
     */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'label'       => $this->label,
            'description' => $this->description,
            'fields'      => $this->fields,
            'searchable'  => $this->searchable,
            'apiEnabled'  => $this->apiEnabled,
            'created_at'  => $this->createdAt,
        ];
    }

    // ── Private ──────────────────────────────────────────────────────

    private static function sanitizeKey(string $name): string
    {
        return preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($name)));
    }

    private static function parseFields(array $raw): array
    {
        $fields = [];
        foreach ($raw as $f) {
            $name = self::sanitizeKey($f['name'] ?? '');
            if ('' === $name) continue;

            $fields[] = [
                'name'       => $name,
                'type'       => $f['type'] ?? 'string',
                'label'      => $f['label'] ?? ucfirst(str_replace('_', ' ', $name)),
                'required'   => (bool) ($f['required'] ?? false),
                'default'    => $f['default'] ?? null,
                'values'     => $f['values'] ?? null,     // for enum
                'target'     => $f['target'] ?? null,      // for relation
                'searchable' => $f['searchable'] ?? true,
                'min'        => $f['min'] ?? null,
                'max'        => $f['max'] ?? null,
            ];
        }
        return $fields;
    }

    private function fieldToSQL(array $field, string $driver): string
    {
        $name    = $field['name'];
        $null    = ($field['required'] ?? false) ? 'NOT NULL' : 'DEFAULT NULL';
        $default = isset($field['default']) ? "DEFAULT " . $this->quoteDefault($field['default']) : '';

        $sqlType = match ($field['type']) {
            'string'   => 'VARCHAR(255)',
            'text'     => 'TEXT',
            'integer'  => 'INTEGER',
            'number'   => 'DECIMAL(10,2)',
            'boolean'  => $driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)',
            'date'     => 'DATE',
            'datetime' => 'DATETIME',
            'enum'     => $this->enumToSQL($field, $driver),
            'relation' => 'INTEGER',
            'json'     => $driver === 'sqlite' ? 'TEXT' : 'JSON',
            default    => 'VARCHAR(255)',
        };

        return trim("{$name} {$sqlType} {$null} {$default}");
    }

    private function enumToSQL(array $field, string $driver): string
    {
        $values = $field['values'] ?? [];
        if ($driver === 'mysql' && !empty($values)) {
            $quoted = array_map(fn($v) => "'" . addslashes($v) . "'", $values);
            return "ENUM(" . implode(',', $quoted) . ")";
        }
        return 'VARCHAR(100)';
    }

    private function quoteDefault(mixed $value): string
    {
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_numeric($value)) return (string) $value;
        if (null === $value) return 'NULL';
        return "'" . addslashes((string) $value) . "'";
    }
}
