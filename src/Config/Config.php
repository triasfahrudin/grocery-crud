<?php

declare(strict_types=1);

namespace GroceryCrud\Config;

use CodeIgniter\Config\BaseConfig;

class Config extends BaseConfig
{
    /**
     * Tema default untuk rendering.
     */
    public string $defaultTheme = 'bootstrap5';

    /**
     * Tema yang terdaftar.
     */
    public array $themes = [
        'bootstrap5'  => \GroceryCrud\Themes\Bootstrap5Theme::class,
        'adminlte4'   => \GroceryCrud\Themes\AdminLTE4Theme::class,
        'tailwind'    => \GroceryCrud\Themes\TailwindTheme::class,
        'materialize' => \GroceryCrud\Themes\MaterializeTheme::class,
    ];

    /**
     * Jumlah default item per halaman.
     */
    public int $perPage = 25;

    /**
     * Opsi per halaman untuk dropdown paginasi.
     *
     * @var int[]
     */
    public array $perPageOptions = [10, 25, 50, 100];

    /**
     * Format tanggal untuk field date/datetime.
     */
    public string $dateFormat = 'Y-m-d';

    public string $datetimeFormat = 'Y-m-d H:i:s';

    /**
     * Konfigurasi upload default.
     */
    public array $uploadConfig = [
        'maxSize'          => 2048, // KB
        'allowedTypes'     => 'jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|csv',
        'uploadPath'       => 'uploads/',
        'thumbnailPath'    => 'uploads/thumbs/',
        'thumbnailWidth'   => 150,
        'thumbnailHeight'  => 150,
        'encryptFileName'  => true,
    ];

    /**
     * Apakah akan menggunakan Datatables untuk daftar.
     */
    public bool $useDatatables = true;

    /**
     * Bahasa default.
     */
    public string $defaultLanguage = 'english';

    /**
     * Bahasa yang tersedia.
     */
    public array $languages = [
        'english'   => \GroceryCrud\Language\English::class,
        'indonesian' => \GroceryCrud\Language\Indonesian::class,
    ];

    /**
     * Tombol aksi default yang ditampilkan.
     */
    public array $defaultActions = ['add', 'edit', 'delete'];

    /**
     * Apakah akan mengaktifkan fungsionalitas ekspor.
     */
    public bool $enableExport = true;

    /**
     * Apakah akan mengaktifkan fungsi tampilan cetak.
     */
    public bool $enablePrintView = true;

    /**
     * Apakah akan mengaktifkan ekspor PDF (membutuhkan dompdf/dompdf).
     */
    public bool $enablePdfExport = true;

    /**
     * Format ekspor.
     */
    public array $exportFormats = ['csv', 'excel', 'pdf', 'print'];

    /**
     * Gaya paginasi: 'simple' atau 'full' (dengan nomor halaman)
     */
    public string $paginationStyle = 'full';

    /**
     * Maksimal record yang diekspor.
     */
    public int $maxExportRecords = 10000;

    /**
     * Apakah akan mengaktifkan fungsionalitas impor.
     */
    public bool $enableImport = true;

    /**
     * Import configuration.
     */
    public array $importConfig = [
        'maxSize'       => 2048, // KB
        'allowedTypes'  => 'csv,xlsx',
        'uploadPath'    => 'uploads/import/',
    ];
}
