<?php

declare(strict_types=1);

namespace GroceryCrud\Lock;

/**
 * Record Lock Manager — penguncian tingkat record untuk lingkungan multi-pengguna.
 *
 * Menyimpan kunci sebagai file JSON di direktori yang dapat ditulis (default: WRITEPATH . 'locks').
 * Setiap file kunci bernama {table}_{recordId}.json dan berisi:
 *   - table, record_id, user_id, user_name, locked_at, expires_at
 *
 * Penggunaan:
 *   $lockManager = new RecordLockManager();
 *   $acquired = $lockManager->acquireLock('products', 5, '1', 'Admin');
 *   $lock = $lockManager->getLock('products', 5); // null jika tidak terkunci atau kedaluwarsa
 *   $lockManager->releaseLock('products', 5);
 */
class RecordLockManager
{
    private string $lockDir;
    private int $lockMinutes;

    /**
     * @param string|null $lockDir Direktori untuk file kunci. Default: WRITEPATH . 'locks' (CI4) atau sys_get_temp_dir() . '/gc-locks'
     * @param int $lockMinutes Menit sebelum kunci kedaluwarsa otomatis (default: 5)
     */
    public function __construct(?string $lockDir = null, int $lockMinutes = 5)
    {
        $this->lockDir = $lockDir ?? $this->getDefaultLockDir();
        $this->lockMinutes = max(1, $lockMinutes);

        if (!is_dir($this->lockDir)) {
            @mkdir($this->lockDir, 0755, true);
        }
    }

    /**
     * Mencoba mengunci sebuah record.
     *
     * @return bool True jika kunci berhasil (atau sudah dimiliki pengguna ini), false jika dikunci pengguna lain.
     */
    public function acquireLock(string $table, mixed $recordId, string $userId, string $userName): bool
    {
        $this->cleanExpiredLocks();
        $lockFile = $this->getLockFile($table, $recordId);

        // Periksa apakah sudah dikunci oleh orang lain
        $existing = $this->getLock($table, $recordId);
        if ($existing !== null && $existing['user_id'] !== $userId) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', strtotime("+{$this->lockMinutes} minutes"));

        file_put_contents($lockFile, json_encode([
            'table'      => $table,
            'record_id'  => (string) $recordId,
            'user_id'    => $userId,
            'user_name'  => $userName,
            'locked_at'  => $now,
            'expires_at' => $expires,
        ], JSON_UNESCAPED_UNICODE));

        return true;
    }

    /**
     * Melepaskan kunci pada sebuah record.
     */
    public function releaseLock(string $table, mixed $recordId): void
    {
        $lockFile = $this->getLockFile($table, $recordId);
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }

    /**
     * Mendapatkan informasi kunci untuk sebuah record, atau null jika tidak terkunci / kedaluwarsa.
     *
     * @return array{table: string, record_id: string, user_id: string, user_name: string, locked_at: string, expires_at: string}|null
     */
    public function getLock(string $table, mixed $recordId): ?array
    {
        $lockFile = $this->getLockFile($table, $recordId);
        if (!file_exists($lockFile)) {
            return null;
        }

        $data = @json_decode(@file_get_contents($lockFile), true);
        if (!is_array($data) || !isset($data['expires_at'])) {
            @unlink($lockFile);
            return null;
        }

        // Check expiry
        if (strtotime($data['expires_at']) < time()) {
            @unlink($lockFile);
            return null;
        }

        return $data;
    }

    /**
     * Menghapus semua file kunci yang kedaluwarsa.
     */
    public function cleanExpiredLocks(): void
    {
        $files = glob($this->lockDir . '/*.json');
        if ($files === false) {
            return;
        }
        foreach ($files as $file) {
            $data = @json_decode(@file_get_contents($file), true);
            if (is_array($data) && isset($data['expires_at']) && strtotime($data['expires_at']) < time()) {
                @unlink($file);
            }
        }
    }

    private function getLockFile(string $table, mixed $recordId): string
    {
        // Sanitize: only alphanumeric, underscore, dash, dot
        $safeTable = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $table);
        $safeId = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', (string) $recordId);
        return $this->lockDir . '/' . $safeTable . '_' . $safeId . '.json';
    }

    private function getDefaultLockDir(): string
    {
        if (defined('WRITEPATH')) {
            $dir = WRITEPATH . 'locks';
        } else {
            $dir = sys_get_temp_dir() . '/gc-locks';
        }
        return $dir;
    }
}
