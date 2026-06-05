<?php

declare(strict_types=1);

namespace GroceryCrud\Models;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use GroceryCrud\Exceptions\GroceryCrudException;

class CrudModel
{
    private BaseConnection $db;
    private string $table;
    private string $primaryKey;
    private string $primaryKeyField;

    /** @var array<string, string> */
    private array $fieldTypes = [];

    /** @var array<string, array<int, string>> */
    private array $enumValues = [];

    /** @var array<string, array<string, mixed>> */
    private array $relationFields = [];

    /** @var array<string, array<string, mixed>> */
    private array $relationNtoN = [];

    public function __construct(BaseConnection $db, string $table)
    {
        $this->db = $db;
        $this->table = $table;
        $this->primaryKey = $this->detectPrimaryKey();
        $this->primaryKeyField = $this->primaryKey;
        $this->loadFieldMetadata();
    }

    /**
     * Detect and return the primary key column name.
     */
    public function detectPrimaryKey(): string
    {
        $fields = $this->db->getFieldData($this->table);
        foreach ($fields as $field) {
            if (!empty($field->primary_key)) {
                return $field->name;
            }
        }
        // Fallback: try common primary key names
        foreach (['id', 'ID', 'Id', $this->table . '_id'] as $candidate) {
            if ($this->db->fieldExists($candidate, $this->table)) {
                return $candidate;
            }
        }

        throw GroceryCrudException::primaryKeyNotFound($this->table);
    }

    /**
     * Get primary key name.
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Load field metadata: types, enum values.
     */
    private function loadFieldMetadata(): void
    {
        $fields = $this->db->getFieldData($this->table);
        foreach ($fields as $field) {
            $this->fieldTypes[$field->name] = $field->type ?? 'text';

            // Detect ENUM values from type string like "enum('val1','val2')"
            if (preg_match('/^enum\((.+)\)$/i', $field->type ?? '', $matches)) {
                $this->parseEnumValues($field->name, $matches[1]);
            }
        }
    }

    /**
     * Parse ENUM values from type definition.
     */
    private function parseEnumValues(string $field, string $typeStr): void
    {
        $values = [];
        preg_match_all("/'([^']*)'/", $typeStr, $matches);
        if (!empty($matches[1])) {
            $this->enumValues[$field] = $matches[1];
        }
    }

    /**
     * Get all column names for the table.
     *
     * @return array<int, string>
     */
    public function getColumnNames(): array
    {
        return array_keys($this->fieldTypes);
    }

    /**
     * Get field type for a specific column.
     */
    public function getFieldType(string $field): ?string
    {
        return $this->fieldTypes[$field] ?? null;
    }

    /**
     * Get all field types.
     *
     * @return array<string, string>
     */
    public function getFieldTypes(): array
    {
        return $this->fieldTypes;
    }

    /**
     * Get enum values for a field.
     *
     * @return array<int, string>
     */
    public function getEnumValues(string $field): array
    {
        return $this->enumValues[$field] ?? [];
    }

    /**
     * Set relation configuration for a field (belongs_to).
     */
    public function setRelationField(string $field, array $config): void
    {
        $this->relationFields[$field] = $config;
    }

    /**
     * Set NtoN relation configuration.
     */
    public function setRelationNtoN(string $field, array $config): void
    {
        $this->relationNtoN[$field] = $config;
    }

    /**
     * Get relation config for a field.
     */
    public function getRelationConfig(string $field): ?array
    {
        return $this->relationFields[$field] ?? null;
    }

    // ======== CRUD Operations ========

    /**
     * Apply column filters to a builder.
     */
    private function applyFilters($builder, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $builder->where($field, $value);
        }
    }

    /**
     * Get paginated, filtered, sorted list of records.
     *
     * @param array<int, string>   $columns
     * @param int                  $limit
     * @param int                  $offset
     * @param array<int, array<string, string>> $orders
     * @param array<string, mixed> $where
     * @param string|null          $search
     * @param array<string, string> $searchableColumns
     * @param array<string, string> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getList(
        array $columns,
        int $limit,
        int $offset,
        array $orders = [],
        array $where = [],
        ?string $search = null,
        array $searchableColumns = [],
        array $filters = []
    ): array {
        $builder = $this->db->table($this->table);

        // Select columns
        $selectColumns = array_merge([$this->primaryKey], $columns);
        $builder->select(array_unique($selectColumns));

        // Where conditions
        foreach ($where as $key => $value) {
            if (is_array($value)) {
                $builder->whereIn($key, $value);
            } else {
                $builder->where($key, $value);
            }
        }

        // Column filters
        $this->applyFilters($builder, $filters);

        // Search
        if ($search !== null && $search !== '' && !empty($searchableColumns)) {
            $builder->groupStart();
            foreach ($searchableColumns as $idx => $col) {
                if ($idx === 0) {
                    $builder->like($col, $search);
                } else {
                    $builder->orLike($col, $search);
                }
            }
            $builder->groupEnd();
        }

        // Order by
        foreach ($orders as $order) {
            $builder->orderBy($order['field'], $order['direction'] ?? 'ASC');
        }

        // Limit / Offset
        if ($limit > 0) {
            $builder->limit($limit, $offset);
        }

        $results = $builder->get()->getResultArray();

        // If there are relation fields, fetch related data
        if (!empty($this->relationFields)) {
            foreach ($results as &$row) {
                foreach ($this->relationFields as $relField => $relConfig) {
                    $relatedValue = $this->fetchRelatedValue(
                        $relConfig,
                        $row[$relField] ?? null
                    );
                    if ($relatedValue !== null) {
                        $row[$relField] = $relatedValue;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Get total record count.
     */
    public function getTotalCount(array $where = [], ?string $search = null, array $searchableColumns = [], array $filters = []): int
    {
        $builder = $this->db->table($this->table);

        foreach ($where as $key => $value) {
            if (is_array($value)) {
                $builder->whereIn($key, $value);
            } else {
                $builder->where($key, $value);
            }
        }

        // Column filters
        $this->applyFilters($builder, $filters);

        if ($search !== null && $search !== '' && !empty($searchableColumns)) {
            $builder->groupStart();
            foreach ($searchableColumns as $idx => $col) {
                if ($idx === 0) {
                    $builder->like($col, $search);
                } else {
                    $builder->orLike($col, $search);
                }
            }
            $builder->groupEnd();
        }

        return $builder->countAllResults();
    }

    /**
     * Delete multiple records by primary key.
     *
     * @param array<int, mixed> $ids
     * @return bool
     */
    public function deleteMultiple(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }

        return $this->db->table($this->table)
            ->whereIn($this->primaryKey, $ids)
            ->delete();
    }

    /**
     * Get a single record by primary key (raw, without relation replacements).
     *
     * @return array<string, mixed>|null
     */
    public function getRawRow(mixed $id): ?array
    {
        $row = $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Get a single record by primary key with relation display values.
     *
     * @return array<string, mixed>|null
     */
    public function getRow(mixed $id): ?array
    {
        $row = $this->getRawRow($id);

        if ($row === null) {
            return null;
        }

        // Fetch relation display values
        if (!empty($this->relationFields)) {
            foreach ($this->relationFields as $relField => $relConfig) {
                $relatedValue = $this->fetchRelatedValue(
                    $relConfig,
                    $row[$relField] ?? null
                );
                if ($relatedValue !== null) {
                    $row[$relField] = $relatedValue;
                }
            }
        }

        return $row;
    }

    /**
     * Insert a new record.
     *
     * @param  array<string, mixed> $data
     * @return mixed Inserted ID
     */
    public function insert(array $data): mixed
    {
        $builder = $this->db->table($this->table);

        // Remove primary key if empty (auto-increment)
        if (isset($data[$this->primaryKey]) && empty($data[$this->primaryKey])) {
            unset($data[$this->primaryKey]);
        }

        $builder->insert($data);
        return $this->db->insertID();
    }

    /**
     * Update a record.
     */
    public function update(mixed $id, array $data): bool
    {
        $builder = $this->db->table($this->table);

        // Remove primary key from data
        unset($data[$this->primaryKey]);

        return $builder->where($this->primaryKey, $id)
            ->update($data);
    }

    /**
     * Delete a record.
     */
    public function delete(mixed $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->delete();
    }

    /**
     * Fetch the display value from a related table.
     */
    private function fetchRelatedValue(array $relConfig, mixed $foreignKey): ?string
    {
        if ($foreignKey === null || $foreignKey === '') {
            return null;
        }

        // Detect the primary key of the related table
        $relatedPk = $this->getPrimaryKeyOfTable($relConfig['relatedTable']);

        $row = $this->db->table($relConfig['relatedTable'])
            ->select($relConfig['relatedTitleField'])
            ->where($relatedPk, $foreignKey)
            ->get()
            ->getRowArray();

        return $row[$relConfig['relatedTitleField']] ?? null;
    }

    /**
     * Get the primary key value from a relation field.
     */
    public function getRelationForeignKey(string $field, mixed $primaryKeyValue): mixed
    {
        $relConfig = $this->relationFields[$field] ?? null;
        if ($relConfig === null) {
            return null;
        }

        $row = $this->db->table($this->table)
            ->select($field)
            ->where($this->primaryKey, $primaryKeyValue)
            ->get()
            ->getRowArray();

        return $row[$field] ?? null;
    }

    /**
     * Get primary key of any table.
     */
    private function getPrimaryKeyOfTable(string $table): string
    {
        $fields = $this->db->getFieldData($table);
        foreach ($fields as $field) {
            if (!empty($field->primary_key)) {
                return $field->name;
            }
        }
        return 'id';
    }

    /**
     * Get full table name with prefix.
     */
    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * Begin transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->db->transBegin();
    }

    /**
     * Commit transaction.
     */
    public function commitTransaction(): bool
    {
        return $this->db->transCommit();
    }

    /**
     * Rollback transaction.
     */
    public function rollbackTransaction(): bool
    {
        return $this->db->transRollback();
    }

    /**
     * Get DB connection.
     */
    public function getDb(): BaseConnection
    {
        return $this->db;
    }
}
