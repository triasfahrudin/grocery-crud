<?php

declare(strict_types=1);

namespace GroceryCrud\Renderers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use GroceryCrud\Config\Config;
use GroceryCrud\Themes\ThemeInterface;

class TableRenderer
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Menyiapkan data tabel untuk rendering tema.
     *
     * @param array<string, mixed> $crudData
     * @return array<string, mixed>
     */
    public function prepareListData(array $crudData): array
    {
        $columns = $crudData['columns'] ?? [];
        $columnLabels = $crudData['columnLabels'] ?? [];
        $records = $crudData['records'] ?? [];
        $totalCount = $crudData['totalCount'] ?? 0;
        $perPage = $crudData['perPage'] ?? $this->config->perPage;
        $currentPage = $crudData['currentPage'] ?? 1;
        $subject = $crudData['subject'] ?? 'Records';
        $primaryKey = $crudData['primaryKey'] ?? 'id';

        // Calculate pagination info
        $totalPages = $perPage > 0 ? (int) ceil($totalCount / $perPage) : 1;
        $from = ($currentPage - 1) * $perPage + 1;
        $to = min($currentPage * $perPage, $totalCount);

        return [
            'columns'              => $columns,
            'columnLabels'         => $columnLabels,
            'records'              => $records,
            'totalCount'           => $totalCount,
            'perPage'              => $perPage,
            'currentPage'          => $currentPage,
            'subject'              => $subject,
            'primaryKey'           => $primaryKey,
            'actions'              => $crudData['actions'] ?? $this->config->defaultActions,
            'customActions'        => $crudData['customActions'] ?? [],
            'searchable'           => $crudData['searchable'] ?? false,
            'useDatatables'        => $crudData['useDatatables'] ?? $this->config->useDatatables,
            'enableExport'         => $crudData['enableExport'] ?? $this->config->enableExport,
            'enableImport'         => $crudData['enableImport'] ?? $this->config->enableImport,
            'exportFormats'        => $crudData['exportFormats'] ?? $this->config->exportFormats,
            'pager'                => [
                'from' => $totalCount > 0 ? $from : 0,
                'to'   => $totalCount > 0 ? $to : 0,
            ],
            'crudId'               => $crudData['crudId'] ?? 'crud_' . uniqid(),
            'sortField'            => $crudData['sortField'] ?? null,
            'sortDir'              => $crudData['sortDir'] ?? null,
            'columnFilters'        => $crudData['columnFilters'] ?? [],
            'currentFilters'       => $crudData['currentFilters'] ?? [],
            'batchActions'         => $crudData['batchActions'] ?? [],
            'softDelete'           => $crudData['softDelete'] ?? false,
            'trashedView'          => $crudData['trashedView'] ?? false,
            'subGrids'             => $crudData['subGrids'] ?? [],
            'fieldOptions'         => $crudData['fieldOptions'] ?? [],
            'enableInlineEditing'  => $crudData['enableInlineEditing'] ?? false,
            'inlineEditFieldTypes' => $crudData['inlineEditFieldTypes'] ?? [],
            'inlineFieldInfo'      => $crudData['inlineFieldInfo'] ?? [],
            'relationPopovers'     => $crudData['relationPopovers'] ?? [],
            'enableActivityLogViewer' => $crudData['enableActivityLogViewer'] ?? false,
            'calendarField'       => $crudData['calendarField'] ?? null,
            'calendarTitleField'  => $crudData['calendarTitleField'] ?? null,
            'enableFileManager'   => $crudData['enableFileManager'] ?? false,
        ];
    }

    /**
     * Merender respons halaman penuh.
     *
     * @param string $headerHtml HTML opsional untuk disisipkan setelah tag <body>.
     */
    public function renderPage(ThemeInterface $theme, array $data, string $headerHtml = ''): ResponseInterface
    {
        $html = $theme->renderList($data);

        $response = service('response');
        $response->setContentType('text/html');
        $response->setBody($this->wrapInPage($html, $theme, $headerHtml));

        return $response;
    }

    /**
     * Membungkus konten dalam halaman HTML penuh (untuk non-AJAX).
     *
     * @param string $headerHtml HTML opsional untuk disisipkan setelah tag <body>.
     */
    private function wrapInPage(string $content, ThemeInterface $theme, string $headerHtml = ''): string
    {
        $cssLinks = '';
        foreach ($theme->getCssFiles() as $css) {
            // Lewati Bootstrap CSS — sudah disertakan secara global untuk navbar
            if (str_contains($css, 'bootstrap')) {
                continue;
            }
            $cssLinks .= '<link rel="stylesheet" href="' . $css . '">' . "\n";
        }

        $jsLinks = '';
        foreach ($theme->getJsFiles() as $js) {
            // Lewati Bootstrap JS — sudah disertakan versi baru secara global
            if (str_contains($js, 'bootstrap')) {
                continue;
            }
            $jsLinks .= '<script src="' . $js . '"></script>' . "\n";
        }

        $themeName = ucfirst($theme->getName());
        $cssV = filemtime(__DIR__ . '/../../assets/css/grocery-crud.css');
        $jsV  = filemtime(__DIR__ . '/../../assets/js/grocery-crud.js');

        $navbarFixCss = <<<'CSS'
<style>
/* Override navbar: lindungi navbar Bootstrap dari tema non-Bootstrap (Materialize, dll.) */
body nav.navbar { height: auto !important; line-height: normal !important; }
body nav.navbar .navbar-brand { color: #fff !important; text-decoration: none !important; display: inline-flex !important; align-items: center !important; }
body nav.navbar .navbar-brand i.bi.bi-grid.me-2 { width: 40px !important; height: 40px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 50% !important; padding: 0 !important; background-color: rgba(255, 255, 255, 0.15) !important; color: #fff !important; font-size: 1.3em !important; }
body nav.navbar .navbar-brand small { opacity: .8 !important; }
body nav.navbar .btn { text-transform: none !important; letter-spacing: normal !important; height: auto !important; line-height: 1.5 !important; }
body nav.navbar .badge { font-weight: 700 !important; line-height: 1 !important; }
body nav.navbar .vr { opacity: .25 !important; }
</style>
CSS;

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$themeName} - Grocery CRUD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    {$navbarFixCss}
    {$cssLinks}
    <link rel="stylesheet" href="/assets/grocery-crud/css/grocery-crud.css?v={$cssV}">
</head>
<body>
{$headerHtml}
    <div class="container-fluid py-4">
        {$content}
    </div>
    {$jsLinks}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/table-dragger@2.0.5/dist/table-dragger.min.js"></script>
    <script src="/assets/grocery-crud/js/grocery-crud.js?v={$jsV}"></script>
</body>
</html>
HTML;
    }
}
