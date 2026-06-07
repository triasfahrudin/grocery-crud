-- =====================================================
-- Migration: Create activity_logs table
-- Audit Trail / Activity Log untuk Grocery CRUD
--
-- Menyimpan log otomatis setiap operasi CRUD:
-- insert, update, delete, restore, batch, import
--
-- Menyimpan:
--   - Siapa (user_id, user_name)
--   - Apa (action, table_name, record_pk)
--   - Kapan (created_at)
--   - Dari mana (ip_address, user_agent)
--   - Data sebelum & sesudah (old_data, new_data) dalam JSON
-- =====================================================

-- Tabel utama activity logs
CREATE TABLE `activity_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `table_name` VARCHAR(255)    NOT NULL COMMENT 'Nama tabel yang dimodifikasi',
    `record_pk`  VARCHAR(255)    NULL     COMMENT 'Primary key value record yang diubah',
    `action`     ENUM('insert', 'update', 'delete', 'restore', 'import')
                                   NOT NULL COMMENT 'Jenis operasi',
    `user_id`    VARCHAR(255)    NULL     COMMENT 'ID user yang melakukan aksi',
    `user_name`  VARCHAR(255)    NULL     COMMENT 'Nama user yang melakukan aksi',
    `old_data`   JSON            NULL     COMMENT 'Data sebelum perubahan (untuk update/delete)',
    `new_data`   JSON            NULL     COMMENT 'Data setelah perubahan (untuk insert/update/import)',
    `ip_address` VARCHAR(45)     NULL     COMMENT 'IP address user',
    `user_agent` TEXT            NULL     COMMENT 'User agent browser',
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu kejadian',

    PRIMARY KEY (`id`),

    -- Index untuk query umum
    INDEX `idx_activity_logs_table`   (`table_name`),
    INDEX `idx_activity_logs_action`  (`action`),
    INDEX `idx_activity_logs_user`    (`user_id`),
    INDEX `idx_activity_logs_date`    (`created_at`),

    -- Composite index untuk pencarian per-record
    INDEX `idx_activity_logs_record`  (`table_name`, `record_pk`),

    -- Index untuk filter aksi dalam rentang waktu
    INDEX `idx_activity_logs_action_date` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit Trail / Activity Log – mencatat semua operasi CRUD otomatis';

-- =====================================================
-- Cara penggunaan di aplikasi CodeIgniter 4:
--
-- 1. Jalankan migration ini di database aplikasi kamu.
--
-- 2. Enable activity log di controller:
--
--    $crud = new GroceryCrud();
--    $crud->setTable('products');
--
--    // Dengan user resolver
--    $crud->enableActivityLog(function () {
--        $session = session();
--        return [
--            'id'   => $session->get('user_id'),
--            'name' => $session->get('user_name'),
--        ];
--    });
--
--    // Atau tanpa user resolver (user_id akan null)
--    $crud->enableActivityLog();
--
--    return $crud->render();
--
-- 3. Untuk melihat logs:
--
--    Daftar logs bisa diambil via ActivityLogManager::getLogs(),
--    atau query langsung ke tabel `activity_logs`.
-- =====================================================
