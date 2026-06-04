<?php

declare(strict_types=1);

namespace GroceryCrud\Relations;

use CodeIgniter\Database\BaseConnection;
use GroceryCrud\Exceptions\GroceryCrudException;

class RelationManager
{
    /** @var array<string, array<string, mixed>> */
    private array $relations = [];

    /** @var array<string, array<string, mixed>> */
    private array $relationNtoN = [];

    private BaseConnection $db;
    private string $primaryTable;
    private string $primaryKey;

    public function __construct(BaseConnection $db, string $primaryTable, string $primaryKey)
    {
        $this->db = $db;
        $this->primaryTable = $primaryTable;
        $this->primaryKey = $primaryKey;
    }

    /**
     * Set a belongs_to relation.
     *
     * @param string      $field      Field name in the main table
     * @param string      $relatedTable  Related table name
     * @param string      $relatedTitleField Title field from related table to display
     * @param string|null $where      Additional WHERE clause
     * @param string|null $orderBy    Order for the related data
     */
    public function setRelation(
        string $field,
        string $relatedTable,
        string $relatedTitleField,
        ?string $where = null,
        ?string $orderBy = null
    ): void {
        $relatedTableClean = $this->cleanTableName($relatedTable);

        $this->relations[$field] = [
            'type'               => 'belongs_to',
            'field'              => $field,
            'relatedTable'       => $relatedTableClean,
            'relatedTitleField'  => $relatedTitleField,
            'foreignKey'         => $field,
            'where'              => $where,
            'orderBy'            => $orderBy,
        ];
    }

    /**
     * Set a many-to-many relation (n to n).
     *
     * @param string $field            Field name (for display)
     * @param string $junctionTable    Junction/pivot table name
     * @param string $primaryKeyInJunction  FK column in junction pointing to main table
     * @param string $foreignKeyInJunction  FK column in junction pointing to target table
     * @param string $targetTable      Target/related table
     * @param string $targetTitleField Title field from target table
     * @param string|null $where       Additional WHERE
     * @param string|null $orderBy     Order
     */
    public function setRelationNtoN(
        string $field,
        string $junctionTable,
        string $primaryKeyInJunction,
        string $foreignKeyInJunction,
        string $targetTable,
        string $targetTitleField,
        ?string $where = null,
        ?string $orderBy = null
    ): void {
        $this->relationNtoN[$field] = [
            'type'                => 'n_to_n',
            'field'               => $field,
            'junctionTable'       => $this->cleanTableName($junctionTable),
            'primaryKeyInJunction'=> $primaryKeyInJunction,
            'foreignKeyInJunction'=> $foreignKeyInJunction,
            'targetTable'         => $this->cleanTableName($targetTable),
            'targetTitleField'    => $targetTitleField,
            'where'               => $where,
            'orderBy'             => $orderBy,
        ];
    }

    /**
     * Get relation data for a belongs_to field.
     *
     * @return array<int, array{id: mixed, title: string}>
     */
    public function getRelationData(string $field): array
    {
        $rel = $this->relations[$field] ?? null;
        if ($rel === null) {
            return [];
        }

        $builder = $this->db->table($rel['relatedTable']);
        $builder->select("{$rel['relatedTable']}.*");

        if ($rel['where'] !== null) {
            $builder->where($rel['where']);
        }

        if ($rel['orderBy'] !== null) {
            $builder->orderBy($rel['orderBy']);
        }

        $results = $builder->get()->getResultArray();

        $data = [];
        foreach ($results as $row) {
            $pk = $this->getPrimaryKeyOfTable($rel['relatedTable']);
            $data[] = [
                'id'    => $row[$pk] ?? null,
                'title' => $row[$rel['relatedTitleField']] ?? '',
            ];
        }

        return $data;
    }

    /**
     * Get selected values for a relation field (for edit form).
     */
    public function getRelationValue(string $field, mixed $primaryKeyValue): mixed
    {
        $rel = $this->relations[$field] ?? null;
        if ($rel === null) {
            return $primaryKeyValue;
        }

        $row = $this->db->table($this->primaryTable)
            ->select($field)
            ->where($this->primaryKey, $primaryKeyValue)
            ->get()
            ->getRowArray();

        return $row[$field] ?? null;
    }

    /**
     * Get NtoN selected values for a field.
     *
     * @return array<int, mixed>
     */
    public function getRelationNtoNValues(string $field, mixed $primaryKeyValue): array
    {
        $rel = $this->relationNtoN[$field] ?? null;
        if ($rel === null) {
            return [];
        }

        $results = $this->db->table($rel['junctionTable'])
            ->select($rel['foreignKeyInJunction'])
            ->where($rel['primaryKeyInJunction'], $primaryKeyValue)
            ->get()
            ->getResultArray();

        return array_column($results, $rel['foreignKeyInJunction']);
    }

    /**
     * Get NtoN relation data for dropdown/checkbox rendering.
     *
     * @return array<int, array{id: mixed, title: string}>
     */
    public function getRelationNtoNData(string $field): array
    {
        $rel = $this->relationNtoN[$field] ?? null;
        if ($rel === null) {
            return [];
        }

        $builder = $this->db->table($rel['targetTable']);
        $builder->select("{$rel['targetTable']}.*");

        if ($rel['where'] !== null) {
            $builder->where($rel['where']);
        }
        if ($rel['orderBy'] !== null) {
            $builder->orderBy($rel['orderBy']);
        }

        $results = $builder->get()->getResultArray();

        $data = [];
        foreach ($results as $row) {
            $pk = $this->getPrimaryKeyOfTable($rel['targetTable']);
            $data[] = [
                'id'    => $row[$pk] ?? null,
                'title' => $row[$rel['targetTitleField']] ?? '',
            ];
        }

        return $data;
    }

    /**
     * Get all relations.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Get all NtoN relations.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRelationNtoN(): array
    {
        return $this->relationNtoN;
    }

    /**
     * Check if a field has a relation.
     */
    public function hasRelation(string $field): bool
    {
        return isset($this->relations[$field]) || isset($this->relationNtoN[$field]);
    }

    /**
     * Get relation type for a field.
     */
    public function getRelationType(string $field): ?string
    {
        if (isset($this->relations[$field])) {
            return 'belongs_to';
        }
        if (isset($this->relationNtoN[$field])) {
            return 'n_to_n';
        }
        return null;
    }

    /**
     * Clean table name (remove prefix if any).
     */
    private function cleanTableName(string $table): string
    {
        return trim($table);
    }

    /**
     * Get primary key of a table.
     */
    private function getPrimaryKeyOfTable(string $table): string
    {
        $fields = $this->db->getFieldData($table);
        foreach ($fields as $field) {
            if ($field->primary_key ?? ($field->key === 'PRI' ?? false)) {
                return $field->name;
            }
        }
        // Fallback: try 'id'
        return 'id';
    }
}
