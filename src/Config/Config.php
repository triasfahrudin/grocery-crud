<?php

declare(strict_types=1);

namespace GroceryCrud\Config;

use CodeIgniter\Config\BaseConfig;

class Config extends BaseConfig
{
    /**
     * Default theme for rendering.
     */
    public string $defaultTheme = 'bootstrap5';

    /**
     * Registered themes.
     */
    public array $themes = [
        'bootstrap5'  => \GroceryCrud\Themes\Bootstrap5Theme::class,
        'adminlte4'   => \GroceryCrud\Themes\AdminLTE4Theme::class,
        'tailwind'    => \GroceryCrud\Themes\TailwindTheme::class,
        'materialize' => \GroceryCrud\Themes\MaterializeTheme::class,
    ];

    /**
     * Default number of items per page.
     */
    public int $perPage = 25;

    /**
     * Per-page options for the pagination dropdown.
     *
     * @var int[]
     */
    public array $perPageOptions = [10, 25, 50, 100];

    /**
     * Date format for date/datetime fields.
     */
    public string $dateFormat = 'Y-m-d';

    public string $datetimeFormat = 'Y-m-d H:i:s';

    /**
     * Default upload configuration.
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
     * Whether to use Datatables for listing.
     */
    public bool $useDatatables = true;

    /**
     * Default language.
     */
    public string $defaultLanguage = 'english';

    /**
     * Available languages.
     */
    public array $languages = [
        'english'   => \GroceryCrud\Language\English::class,
        'indonesian' => \GroceryCrud\Language\Indonesian::class,
    ];

    /**
     * Default action buttons to show.
     */
    public array $defaultActions = ['add', 'edit', 'delete'];

    /**
     * Whether to enable export functionality.
     */
    public bool $enableExport = true;

    /**
     * Whether to enable print view function.
     */
    public bool $enablePrintView = true;

    /**
     * Whether to enable PDF export (requires dompdf/dompdf).
     */
    public bool $enablePdfExport = true;

    /**
     * Export formats.
     */
    public array $exportFormats = ['csv', 'excel', 'pdf', 'print'];

    /**
     * Pagination style: 'simple' or 'full' (with page numbers)
     */
    public string $paginationStyle = 'full';

    /**
     * Max records to export.
     */
    public int $maxExportRecords = 10000;

    /**
     * Whether to enable import functionality.
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
