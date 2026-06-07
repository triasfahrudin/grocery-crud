<?php

declare(strict_types=1);

namespace GroceryCrud\ActivityLog;

use CodeIgniter\Database\BaseConnection;

/**
 * ActivityLogManager — Audit Trail untuk Grocery CRUD.
 *
 * Mencatat otomatis setiap operasi CRUD (insert, update, delete, restore, batch)
 * termasuk data sebelum dan sesudah, serta informasi user.
 *
 * Penggunaan di aplikasi:
 *   $crud->enableActivityLog(function () {
 *       return ['id' => user_id(), 'name' => user_name()];
 *   });
 */
class ActivityLogManager
{
    private BaseConnection $db;
    private string $tableName = 'activity_logs';

    /** @var ?callable(): array{id: string|null, name: string|null} */
    private $userResolver = null;

    /** @var array<string, string> Field labels untuk human-readable diff */
    private array $fieldLabels = [];

    /** @var array<int, string> Field names yang harus di-exclude dari log */
    private array $excludeFields = ['password', 'password_hash', 'passwd'];

    /** @var bool Apakah logging aktif */
    private bool $enabled = true;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Set custom table name untuk activity logs.
     */
    public function setTableName(string $tableName): self
    {
        $this->tableName = $tableName;
        return $this;
    }

    /**
     * Dapatkan nama tabel activity logs.
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Set resolver untuk mendapatkan user current.
     *
     * Callback harus mengembalikan array dengan key 'id' dan 'name':
     *   ['id' => '1', 'name' => 'Admin']
     *
     * @param callable(): array{id: string|null, name: string|null} $resolver
     */
    public function setUserResolver(callable $resolver): self
    {
        $this->userResolver = $resolver;
        return $this;
    }

    /**
     * Set field labels untuk human-readable diff.
     *
     * @param array<string, string> $labels
     */
    public function setFieldLabels(array $labels): self
    {
        $this->fieldLabels = $labels;
        return $this;
    }

    /**
     * Set field names yang harus dikecualikan dari log (tidak dicatat nilainya).
     *
     * @param array<int, string> $fields
     */
    public function setExcludeFields(array $fields): self
    {
        $this->excludeFields = $fields;
        return $this;
    }

    /**
     * Tambah field name ke daftar exclude.
     */
    public function addExcludeField(string $field): self
    {
        $this->excludeFields[] = $field;
        return $this;
    }

    /**
     * Aktifkan/nonaktifkan logging.
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Log insert record.
     *
     * @param string $table Nama tabel
     * @param mixed  $recordPk Primary key value record yang di-insert
     * @param array<string, mixed> $newData Data yang di-insert
     */
    public function logInsert(string $table, mixed $recordPk, array $newData): ?int
    {
        if (!$this->enabled) {
            return null;
        }

        return $this->writeLog([
            'table_name'  => $table,
            'record_pk'   => (string) $recordPk,
            'action'      => 'insert',
            'old_data'    => null,
            'new_data'    => $this->sanitizeData($newData),
        ]);
    }

    /**
     * Log update record.
     *
     * @param string $table Nama tabel
     * @param mixed  $recordPk Primary key value
     * @param array<string, mixed> $oldData Data sebelum update
     * @param array<string, mixed> $newData Data setelah update (yang dikirim form)
     */
    public function logUpdate(string $table, mixed $recordPk, array $oldData, array $newData): ?int
    {
        if (!$this->enabled) {
            return null;
        }

        return $this->writeLog([
            'table_name'  => $table,
            'record_pk'   => (string) $recordPk,
            'action'      => 'update',
            'old_data'    => $this->sanitizeData($oldData),
            'new_data'    => $this->sanitizeData($newData),
        ]);
    }

    /**
     * Log delete record.
     *
     * @param string $table Nama tabel
     * @param mixed  $recordPk Primary key value record yang dihapus
     * @param array<string, mixed>|null $oldData Data sebelum dihapus (null jika tidak tersedia)
     */
    public function logDelete(string $table, mixed $recordPk, ?array $oldData = null): ?int
    {
        if (!$this->enabled) {
            return null;
        }

        return $this->writeLog([
            'table_name'  => $table,
            'record_pk'   => (string) $recordPk,
            'action'      => 'delete',
            'old_data'    => $oldData ? $this->sanitizeData($oldData) : null,
            'new_data'    => null,
        ]);
    }

    /**
     * Log restore record (soft delete -> active).
     *
     * @param string $table Nama tabel
     * @param mixed  $recordPk Primary key value
     */
    public function logRestore(string $table, mixed $recordPk): ?int
    {
        if (!$this->enabled) {
            return null;
        }

        return $this->writeLog([
            'table_name'  => $table,
            'record_pk'   => (string) $recordPk,
            'action'      => 'restore',
            'old_data'    => null,
            'new_data'    => null,
        ]);
    }

    /**
     * Log batch delete.
     *
     * @param string $table Nama tabel
     * @param array<int, mixed> $recordPks Array of primary key values
     */
    public function logBatchDelete(string $table, array $recordPks): void
    {
        if (!$this->enabled || empty($recordPks)) {
            return;
        }

        foreach ($recordPks as $pk) {
            $this->logDelete($table, $pk);
        }
    }

    /**
     * Log batch restore.
     *
     * @param string $table Nama tabel
     * @param array<int, mixed> $recordPks Array of primary key values
     */
    public function logBatchRestore(string $table, array $recordPks): void
    {
        if (!$this->enabled || empty($recordPks)) {
            return;
        }

        foreach ($recordPks as $pk) {
            $this->logRestore($table, $pk);
        }
    }

    /**
     * Log import.
     *
     * @param string $table Nama tabel
     * @param int    $importedCount Jumlah record berhasil di-import
     * @param int    $totalCount Jumlah total record dalam file
     */
    public function logImport(string $table, int $importedCount, int $totalCount): ?int
    {
        if (!$this->enabled) {
            return null;
        }

        return $this->writeLog([
            'table_name'  => $table,
            'record_pk'   => null,
            'action'      => 'import',
            'old_data'    => null,
            'new_data'    => json_encode([
                'imported' => $importedCount,
                'total'    => $totalCount,
            ]),
        ]);
    }

    // ======== Query Methods ========

    /**
     * Get paginated activity logs.
     *
     * @param array<string, mixed> $filters [table_name?, action?, user_id?, date_from?, date_to?]
     * @param int  $page
     * @param int  $perPage
     * @param string $sortField
     * @param string $sortDir
     * @return array{logs: list<array<string, mixed>>, total: int}
     */
    public function getLogs(
        array $filters = [],
        int $page = 1,
        int $perPage = 50,
        string $sortField = 'created_at',
        string $sortDir = 'DESC'
    ): array {
        $builder = $this->db->table($this->tableName);

        // Apply filters
        if (!empty($filters['table_name'])) {
            $builder->where('table_name', $filters['table_name']);
        }
        if (!empty($filters['action'])) {
            $builder->where('action', $filters['action']);
        }
        if (!empty($filters['user_id'])) {
            $builder->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['date_from'])) {
            $builder->where('created_at >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $builder->where('created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        $total = $builder->countAllResults(false);

        $allowedSortFields = ['created_at', 'table_name', 'action', 'user_name'];
        $sortField = in_array($sortField, $allowedSortFields, true) ? $sortField : 'created_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $logs = $builder
            ->orderBy($sortField, $sortDir)
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        // Decode JSON data
        foreach ($logs as &$log) {
            if (isset($log['old_data']) && is_string($log['old_data'])) {
                $log['old_data'] = json_decode($log['old_data'], true);
            }
            if (isset($log['new_data']) && is_string($log['new_data'])) {
                $log['new_data'] = json_decode($log['new_data'], true);
            }
        }

        return [
            'logs'  => $logs,
            'total' => (int) $total,
        ];
    }

    /**
     * Get distinct table names that have logs.
     *
     * @return array<int, string>
     */
    public function getLoggedTables(): array
    {
        return $this->db->table($this->tableName)
            ->select('table_name')
            ->distinct()
            ->orderBy('table_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Generate human-readable diff dari old_data vs new_data.
     *
     * @param array<string, mixed>|null $oldData
     * @param array<string, mixed>|null $newData
     * @return array<int, array{field: string, label: string, old: mixed, new: mixed}>
     */
    public function diff(?array $oldData, ?array $newData): array
    {
        $changes = [];

        if ($oldData === null && $newData === null) {
            return $changes;
        }

        $allKeys = array_unique(
            array_merge(
                $oldData ? array_keys($oldData) : [],
                $newData ? array_keys($newData) : []
            )
        );

        foreach ($allKeys as $key) {
            $oldVal = $oldData[$key] ?? null;
            $newVal = $newData[$key] ?? null;

            if ($oldVal !== $newVal) {
                $changes[] = [
                    'field' => $key,
                    'label' => $this->fieldLabels[$key] ?? $key,
                    'old'   => $oldVal,
                    'new'   => $newVal,
                ];
            }
        }

        return $changes;
    }

    /**
     * Purge logs older than specified date.
     *
     * @param string $date Date string (Y-m-d)
     * @return int Jumlah record yang dihapus
     */
    public function purgeOlderThan(string $date): int
    {
        $this->db->table($this->tableName)
            ->where('created_at <', $date . ' 00:00:00')
            ->delete();

        return $this->db->affectedRows();
    }

    /**
     * Hapus semua logs untuk tabel tertentu.
     */
    public function purgeTable(string $table): int
    {
        $this->db->table($this->tableName)
            ->where('table_name', $table)
            ->delete();

        return $this->db->affectedRows();
    }

    // ======== Internal ========

    /**
     * Write log ke database.
     *
     * @param array<string, mixed> $data
     * @return int Insert ID
     */
    private function writeLog(array $data): int
    {
        $user = $this->resolveUser();
        $request = \Config\Services::request();

        $ipAddress = null;
        $userAgent = null;

        if ($request instanceof \CodeIgniter\HTTP\RequestInterface) {
            $ipAddress = $request->getIPAddress();

            // getUserAgent() tersedia di IncomingRequest
            if (method_exists($request, 'getUserAgent')) {
                $agent = $request->getUserAgent();
                if ($agent !== null && method_exists($agent, 'getAgentString')) {
                    $userAgent = mb_substr((string) $agent->getAgentString(), 0, 500);
                }
            }
        }

        $logData = array_merge($data, [
            'user_id'    => $user['id'] ?? null,
            'user_name'  => $user['name'] ?? null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Encode JSON data
        if (isset($logData['old_data']) && is_array($logData['old_data'])) {
            $logData['old_data'] = json_encode($logData['old_data']);
        }
        if (isset($logData['new_data']) && is_array($logData['new_data'])) {
            $logData['new_data'] = json_encode($logData['new_data']);
        }

        $this->db->table($this->tableName)->insert($logData);

        return (int) $this->db->insertID();
    }

    /**
     * Resolve current user dari callback.
     *
     * @return array{id: string|null, name: string|null}
     */
    private function resolveUser(): array
    {
        if ($this->userResolver !== null) {
            $result = call_user_func($this->userResolver);
            return [
                'id'   => $result['id'] ?? null,
                'name' => $result['name'] ?? null,
            ];
        }

        return ['id' => null, 'name' => null];
    }

    /**
     * Sanitize data: remove excluded fields, limit size.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Skip excluded fields
            if (in_array($key, $this->excludeFields, true)) {
                continue;
            }

            // Skip internal keys
            if (str_starts_with((string) $key, '_') || str_ends_with((string) $key, '_existing')) {
                continue;
            }

            // Encode complex types to string
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            // Truncate very long values
            if (is_string($value) && strlen($value) > 10000) {
                $value = mb_substr($value, 0, 10000) . '...[truncated]';
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
