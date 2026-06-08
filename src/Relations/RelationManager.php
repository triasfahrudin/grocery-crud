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
     * Mengatur relasi belongs_to.
     *
     * @param string      $field      Nama field di tabel utama
     * @param string      $relatedTable  Nama tabel terkait
     * @param string      $relatedTitleField Field judul dari tabel terkait untuk ditampilkan
     * @param string|null $where      Klausa WHERE tambahan
     * @param string|null $orderBy    Urutan untuk data terkait
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
     * Mengatur relasi many-to-many (n to n).
     *
     * @param string $field            Nama field (untuk tampilan)
     * @param string $junctionTable    Nama tabel junction/pivot
     * @param string $primaryKeyInJunction  Kolom FK di junction yang mengarah ke tabel utama
     * @param string $foreignKeyInJunction  Kolom FK di junction yang mengarah ke tabel target
     * @param string $targetTable      Tabel target/terkait
     * @param string $targetTitleField Field judul dari tabel target
     * @param string|null $where       WHERE tambahan
     * @param string|null $orderBy     Urutan
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
     * Mendapatkan data relasi untuk field belongs_to.
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
     * Mendapatkan nilai terpilih untuk field relasi (untuk form edit).
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
     * Mendapatkan nilai terpilih NtoN untuk sebuah field.
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
     * Mendapatkan data relasi NtoN untuk rendering dropdown/checkbox.
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
     * Mendapatkan semua relasi.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Mendapatkan semua relasi NtoN.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getRelationNtoN(): array
    {
        return $this->relationNtoN;
    }

    /**
     * Memeriksa apakah sebuah field memiliki relasi.
     */
    public function hasRelation(string $field): bool
    {
        return isset($this->relations[$field]) || isset($this->relationNtoN[$field]);
    }

    /**
     * Mendapatkan tipe relasi untuk sebuah field.
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
     * Mendapatkan info relasi lengkap untuk field belongs_to.
     *
     * @return array{relatedTable: string, relatedTitleField: string, keyField: string, foreignKey: string}|null
     */
    public function getRelationInfo(string $field): ?array
    {
        if (!isset($this->relations[$field])) {
            return null;
        }

        $rel = $this->relations[$field];

        return [
            'relatedTable'      => $rel['relatedTable'],
            'relatedTitleField' => $rel['relatedTitleField'],
            'foreignKey'        => $rel['foreignKey'],
            'keyField'          => $this->getPrimaryKeyOfTable($rel['relatedTable']),
        ];
    }

    /**
     * Membersihkan nama tabel (menghapus prefiks jika ada).
     */
    private function cleanTableName(string $table): string
    {
        return trim($table);
    }

    /**
     * Mendapatkan primary key dari sebuah tabel.
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
