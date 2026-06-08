<?php

declare(strict_types=1);

namespace GroceryCrud\Language;

class Indonesian
{
    public array $strings = [
        'add_record'        => 'Tambah Data',
        'edit_record'       => 'Ubah Data',
        'delete_record'     => 'Hapus Data',
        'cancel'            => 'Batal',
        'save'              => 'Simpan',
        'add'               => 'Tambah',
        'edit'              => 'Ubah',
        'delete'            => 'Hapus',
        'view'              => 'Lihat',
        'search'            => 'Cari',
        'reset'             => 'Reset',
        'actions'           => 'Aksi',
        'no_records'        => 'Tidak ada data.',
        'records'           => 'data',
        'to'                => 'sampai',
        'of'                => 'dari',
        'page'              => 'Halaman',
        'first'             => 'Pertama',
        'last'              => 'Terakhir',
        'previous'          => 'Sebelumnya',
        'next'              => 'Selanjutnya',
        'per_page'          => 'per halaman',
        'loading'           => 'Memuat...',
        'processing'        => 'Memproses...',
        'confirm_delete'    => 'Apakah Anda yakin ingin menghapus data ini?',
        'confirm_delete_multiple' => 'Apakah Anda yakin ingin menghapus data yang dipilih?',
        'delete_success'    => 'Data berhasil dihapus.',
        'delete_fail'       => 'Gagal menghapus data.',
        'insert_success'    => 'Data berhasil ditambahkan.',
        'insert_fail'       => 'Gagal menambahkan data.',
        'update_success'    => 'Data berhasil diperbarui.',
        'update_fail'       => 'Gagal memperbarui data.',
        'upload_error'      => 'Terjadi kesalahan saat upload file.',
        'invalid_file_type' => 'Tipe file tidak valid.',
        'file_too_large'    => 'Ukuran file melebihi batas.',
        'required'          => 'Field ini wajib diisi.',
        'unique'            => 'Nilai ini sudah ada.',
        'export'            => 'Ekspor',
        'export_csv'        => 'Ekspor ke CSV',
        'export_excel'      => 'Ekspor ke Excel',
        'export_pdf'        => 'Ekspor ke PDF',
        'print_view'        => 'Tampilan Cetak',
        'filter'            => 'Filter',
        'all'               => 'Semua',
        'print'             => 'Cetak',
        'select_all'        => 'Pilih Semua',
        'deselect_all'      => 'Batalkan Semua',
        'selected'          => 'dipilih',
        'no_item_selected'  => 'Tidak ada item dipilih.',

        'batch_success'     => 'Aksi batch berhasil dilakukan.',
        'batch_fail'        => 'Aksi batch gagal.',

        // Soft Delete
        'restore'           => 'Pulihkan',
        'restore_success'   => 'Data berhasil dipulihkan.',
        'restore_fail'      => 'Gagal memulihkan data.',
        'trash_list'        => 'Sampah',
        'active_list'       => 'Data Aktif',

        // RBAC / Permissions
        'permission_denied' => 'Anda tidak memiliki izin untuk melakukan aksi ini.',

        // Import
        'import'                => 'Impor',
        'import_csv_excel'      => 'Impor dari CSV / Excel',
        'import_upload'         => 'Upload File',
        'import_upload_hint'    => 'Pilih file CSV atau Excel (.xlsx) untuk diimpor.',
        'import_column_mapping' => 'Pemetaan Kolom',
        'import_preview'        => 'Pratinjau',
        'import_total_rows'     => 'Total baris dalam file',
        'import_map_to'         => 'Petakan ke',
        'import_not_mapped'     => 'Tidak dipetakan',
        'import_execute'        => 'Impor Data',
        'import_success'        => 'Berhasil mengimpor {imported} dari {total} data.',
        'import_error'          => 'Impor gagal. Terjadi {errors} kesalahan.',
        'import_no_data'        => 'Tidak ada data untuk diimpor.',
        'import_file_required'  => 'Silakan pilih file untuk diimpor.',
        'import_confirm'        => 'Apakah Anda yakin ingin mengimpor {total} data?',

        // Activity Log / Audit Trail
        'activity_log'          => 'Log Aktivitas',
        'activity_logs'         => 'Log Aktivitas',
        'activity_log_empty'    => 'Belum ada aktivitas tercatat.',
        'activity_log_action_insert' => 'Ditambahkan',
        'activity_log_action_update' => 'Diubah',
        'activity_log_action_delete' => 'Dihapus',
        'activity_log_action_restore' => 'Dipulihkan',
        'activity_log_action_import' => 'Diimpor',
        'activity_log_table'    => 'Tabel',
        'activity_log_record'   => 'Data',
        'activity_log_user'     => 'Pengguna',
        'activity_log_action'   => 'Aksi',
        'activity_log_date'     => 'Tanggal',
        'activity_log_ip'       => 'Alamat IP',
        'activity_log_old_value' => 'Nilai Lama',
        'activity_log_new_value' => 'Nilai Baru',
        'activity_log_no_changes' => 'Tidak ada perubahan',
        'activity_log_detail'   => 'Detail',

        // Export Selected Columns
        'export_select_columns'     => 'Pilih Kolom untuk Diekspor',
        'export_selected'           => 'Ekspor yang Dipilih',
        'select_columns_hint'       => 'Pilih kolom yang akan dimasukkan ke dalam ekspor.',
        'export_no_column_selected' => 'Silakan pilih setidaknya satu kolom.',

        // Field Groups
        'general'                   => 'Umum',

        // Calendar View
        'calendar_view'             => 'Tampilan Kalender',
        'table_view'                => 'Tampilan Tabel',
    ];
}
