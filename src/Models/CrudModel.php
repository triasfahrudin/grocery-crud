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

    // ======== Soft Delete Properties ========
    private bool $softDelete = false;
    private string $trashedFilter = 'exclude'; // 'exclude', 'with', 'only'
    private string $softDeleteField = 'deleted_at';

    /** @var array<string, array<string, mixed>> */
    private array $relationFields = [];

    /** @var array<string, array<string, mixed>> */
    private array $relationNtoN = [];

    /** @var array<string, array<string, mixed>> */
    private array $subGrids = [];

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

    // ======== Sub-Grid ========

    /**
     * Register a sub-grid configuration.
     *
     * @param string $field      Virtual field name (identifier)
     * @param array  $config     ['relatedTable', 'foreignKey', 'columns', 'columnLabels', 'primaryKey']
     */
    public function setSubGrid(string $field, array $config): void
    {
        $this->subGrids[$field] = $config;
    }

    /**
     * Get sub-grid configs.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSubGrids(): array
    {
        return $this->subGrids;
    }

    /**
     * Fetch sub-grid data for a parent record.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSubGridData(string $field, mixed $parentId): array
    {
        $config = $this->subGrids[$field] ?? null;
        if ($config === null) {
            return [];
        }

        $relatedTable     = $config['relatedTable'];
        $foreignKey       = $config['foreignKey'];
        $columns          = $config['columns'] ?? [];
        $columnRelations  = $config['columnRelations'] ?? [];
        $pk               = $config['primaryKey'] ?? $this->getPrimaryKeyOfTable($relatedTable);
        $select           = [$relatedTable . '.' . $pk];
        $builder          = $this->db->table($relatedTable);

        foreach ($columns as $col) {
            if (isset($columnRelations[$col])) {
                [$relTable, $relDisplay, $relLocalKey, $relForeign] = $columnRelations[$col];
                $alias = $relTable . '_' . $col;
                $builder->join(
                    "{$relTable} AS {$alias}",
                    "{$alias}.{$relForeign} = {$relatedTable}.{$relLocalKey}",
                    'left'
                );
                $select[] = "{$alias}.{$relDisplay} AS {$col}";
            } else {
                $select[] = $relatedTable . '.' . $col;
            }
        }

        return $builder
            ->select($select)
            ->where($foreignKey, $parentId)
            ->get()
            ->getResultArray();
    }

    // ======== Soft Delete ========

    /**
     * Enable or disable soft delete.
     */
    public function setSoftDelete(bool $enabled = true): self
    {
        $this->softDelete = $enabled;
        return $this;
    }

    /**
     * Include soft-deleted records in queries (no filter).
     */
    public function withTrashed(): self
    {
        $this->trashedFilter = 'with';
        return $this;
    }

    /**
     * Only show soft-deleted records.
     */
    public function onlyTrashed(): self
    {
        $this->trashedFilter = 'only';
        return $this;
    }

    /**
     * Restore a soft-deleted record by primary key.
     */
    public function restore(mixed $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->update([$this->softDeleteField => null]);
    }

    /**
     * Permanently delete a record (hard delete regardless of soft delete setting).
     */
    public function forceDelete(mixed $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->delete();
    }

    /**
     * Apply soft delete filter to a query builder.
     */
    private function applySoftDeleteFilter($builder): void
    {
        if (!$this->softDelete) {
            return;
        }
        if ($this->trashedFilter === 'exclude') {
            $builder->where($this->softDeleteField, null);
        } elseif ($this->trashedFilter === 'only') {
            $builder->where($this->softDeleteField . ' IS NOT NULL');
        }
        // 'with' = no filter
    }

    // ======== CRUD Operations ========

    /**
     * Apply column filters to a builder.
     */
    private array $filterTypes = [];

    public function setFilterTypes(array $types): void
    {
        $this->filterTypes = $types;
    }

    /** @var array<int, array{field: string, operator: string, value: string}> */
    private array $advancedFilters = [];

    public function addAdvancedFilter(string $field, string $operator, string $value): void
    {
        $this->advancedFilters[] = ['field' => $field, 'operator' => $operator, 'value' => $value];
    }

    private function applyFilters($builder, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $type = $this->filterTypes[$field] ?? 'dropdown';
            if ($type === 'text') {
                $builder->like($field, $value);
            } else {
                $builder->where($field, $value);
            }
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

        // Soft delete filter
        $this->applySoftDeleteFilter($builder);

        // Column filters
        $this->applyFilters($builder, $filters);

        // Apply advanced filters
        foreach ($this->advancedFilters as $af) {
            $field = $af['field'];
            $value = $af['value'];
            $operator = $af['operator'];

            switch ($operator) {
                case 'equals':
                    $builder->where($field, $value);
                    break;
                case 'not_equal':
                    $builder->where($field . ' !=', $value);
                    break;
                case 'starts_with':
                    $builder->like($field, $value, 'after');
                    break;
                case 'ends_with':
                    $builder->like($field, $value, 'before');
                    break;
                case 'greater_than':
                    $builder->where($field . ' >', $value);
                    break;
                case 'less_than':
                    $builder->where($field . ' <', $value);
                    break;
                case 'contains':
                default:
                    $builder->like($field, $value);
                    break;
            }
        }

        // Search (supports relation fields: fetch matching FK values from related table)
        if ($search !== null && $search !== '' && !empty($searchableColumns)) {
            $builder->groupStart();
            foreach ($searchableColumns as $idx => $col) {
                if (isset($this->relationFields[$col])) {
                    // For relation fields, query the related table directly for matching FKs
                    $relConfig    = $this->relationFields[$col];
                    $relatedTable = $relConfig['relatedTable'];
                    $relatedTitle = $relConfig['relatedTitleField'];
                    $foreignKey   = $relConfig['foreignKey'];
                    $relatedPk    = $this->getPrimaryKeyOfTable($relatedTable);

                    $relatedRows = $this->db->table($relatedTable)
                        ->select($relatedPk)
                        ->like($relatedTitle, $search)
                        ->get()
                        ->getResultArray();

                    $fkValues = array_column($relatedRows, $relatedPk);

                    if (!empty($fkValues)) {
                        if ($idx === 0) {
                            $builder->whereIn("$this->table.$foreignKey", $fkValues);
                        } else {
                            $builder->orWhereIn("$this->table.$foreignKey", $fkValues);
                        }
                    }
                } else {
                    if ($idx === 0) {
                        $builder->like($col, $search);
                    } else {
                        $builder->orLike($col, $search);
                    }
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

        // If there are relation fields, fetch related data in batch (eliminate N+1)
        if (!empty($this->relationFields)) {
            foreach ($this->relationFields as $relField => $relConfig) {
                $relatedTable      = $relConfig['relatedTable'];
                $relatedTitleField = $relConfig['relatedTitleField'];
                $foreignKey        = $relConfig['foreignKey'];
                $relatedPk         = $this->getPrimaryKeyOfTable($relatedTable);

                // Collect all FK values
                $fkValues = array_unique(array_filter(array_column($results, $foreignKey)));

                if (!empty($fkValues)) {
                    // Batch fetch all related titles
                    $relatedRows = $this->db->table($relatedTable)
                        ->select("$relatedPk, $relatedTitleField")
                        ->whereIn($relatedPk, $fkValues)
                        ->get()
                        ->getResultArray();

                    // Build lookup map
                    $lookup = [];
                    foreach ($relatedRows as $rr) {
                        $lookup[$rr[$relatedPk]] = $rr[$relatedTitleField];
                    }

                    // Replace FK values with display values
                    foreach ($results as &$row) {
                        if (isset($lookup[$row[$relField]])) {
                            $row[$relField] = $lookup[$row[$relField]];
                        }
                    }
                }
            }
        }

        $this->advancedFilters = [];
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

        // Soft delete filter
        $this->applySoftDeleteFilter($builder);

        // Column filters
        $this->applyFilters($builder, $filters);

        // Apply advanced filters
        foreach ($this->advancedFilters as $af) {
            $field = $af['field'];
            $value = $af['value'];
            $operator = $af['operator'];

            switch ($operator) {
                case 'equals':
                    $builder->where($field, $value);
                    break;
                case 'not_equal':
                    $builder->where($field . ' !=', $value);
                    break;
                case 'starts_with':
                    $builder->like($field, $value, 'after');
                    break;
                case 'ends_with':
                    $builder->like($field, $value, 'before');
                    break;
                case 'greater_than':
                    $builder->where($field . ' >', $value);
                    break;
                case 'less_than':
                    $builder->where($field . ' <', $value);
                    break;
                case 'contains':
                default:
                    $builder->like($field, $value);
                    break;
            }
        }

        // Search (supports relation fields: fetch matching FK values from related table)
        if ($search !== null && $search !== '' && !empty($searchableColumns)) {
            $builder->groupStart();
            foreach ($searchableColumns as $idx => $col) {
                if (isset($this->relationFields[$col])) {
                    $relConfig    = $this->relationFields[$col];
                    $relatedTable = $relConfig['relatedTable'];
                    $relatedTitle = $relConfig['relatedTitleField'];
                    $foreignKey   = $relConfig['foreignKey'];
                    $relatedPk    = $this->getPrimaryKeyOfTable($relatedTable);

                    $relatedRows = $this->db->table($relatedTable)
                        ->select($relatedPk)
                        ->like($relatedTitle, $search)
                        ->get()
                        ->getResultArray();

                    $fkValues = array_column($relatedRows, $relatedPk);

                    if (!empty($fkValues)) {
                        if ($idx === 0) {
                            $builder->whereIn("$this->table.$foreignKey", $fkValues);
                        } else {
                            $builder->orWhereIn("$this->table.$foreignKey", $fkValues);
                        }
                    }
                } else {
                    if ($idx === 0) {
                        $builder->like($col, $search);
                    } else {
                        $builder->orLike($col, $search);
                    }
                }
            }
            $builder->groupEnd();
        }

        $this->advancedFilters = [];
        return $builder->countAllResults();
    }

    /**
     * Restore multiple soft-deleted records by primary key.
     *
     * @param array<int, mixed> $ids
     * @return bool
     */
    public function restoreMultiple(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }

        return $this->db->table($this->table)
            ->whereIn($this->primaryKey, $ids)
            ->update([$this->softDeleteField => null]);
    }

    /**
     * Permanently delete multiple records (bypass soft delete).
     *
     * @param array<int, mixed> $ids
     * @return bool
     */
    public function forceDeleteMultiple(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }

        return $this->db->table($this->table)
            ->whereIn($this->primaryKey, $ids)
            ->delete();
    }

    /**
     * Delete multiple records by primary key (soft delete if enabled).
     *
     * @param array<int, mixed> $ids
     * @return bool
     */
    public function deleteMultiple(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }

        if ($this->softDelete) {
            return $this->db->table($this->table)
                ->whereIn($this->primaryKey, $ids)
                ->update([$this->softDeleteField => date('Y-m-d H:i:s')]);
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
     * Delete a record (soft delete if enabled, otherwise hard delete).
     */
    public function delete(mixed $id): bool
    {
        if ($this->softDelete) {
            return $this->db->table($this->table)
                ->where($this->primaryKey, $id)
                ->update([$this->softDeleteField => date('Y-m-d H:i:s')]);
        }

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

    // ======== Repeater HasMany Helpers ========

    /**
     * Get related rows for a hasMany repeater.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRelatedRows(string $relatedTable, string $foreignKey, mixed $parentId): array
    {
        $pk = $this->getPrimaryKeyOfTable($relatedTable);

        return $this->db->table($relatedTable)
            ->where($foreignKey, $parentId)
            ->orderBy($pk, 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Delete all related rows for a hasMany relationship.
     */
    public function deleteRelatedRows(string $relatedTable, string $foreignKey, mixed $parentId): bool
    {
        return $this->db->table($relatedTable)
            ->where($foreignKey, $parentId)
            ->delete();
    }

    /**
     * Insert a row into a related table.
     */
    public function insertRelatedRow(string $relatedTable, array $data): bool|int
    {
        return $this->db->table($relatedTable)
            ->insert($data);
    }
}
