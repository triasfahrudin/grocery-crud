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
     * Prepare table data for theme rendering.
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
            'columns'        => $columns,
            'columnLabels'   => $columnLabels,
            'records'        => $records,
            'totalCount'     => $totalCount,
            'perPage'        => $perPage,
            'currentPage'    => $currentPage,
            'subject'        => $subject,
            'primaryKey'     => $primaryKey,
            'actions'        => $crudData['actions'] ?? $this->config->defaultActions,
            'customActions'  => $crudData['customActions'] ?? [],
            'searchable'     => $crudData['searchable'] ?? false,
            'useDatatables'  => $crudData['useDatatables'] ?? $this->config->useDatatables,
            'enableExport'   => $crudData['enableExport'] ?? $this->config->enableExport,
            'exportFormats'  => $crudData['exportFormats'] ?? $this->config->exportFormats,
            'pager'          => [
                'from' => $totalCount > 0 ? $from : 0,
                'to'   => $totalCount > 0 ? $to : 0,
            ],
            'crudId'         => $crudData['crudId'] ?? 'crud_' . uniqid(),
            'sortField'      => $crudData['sortField'] ?? null,
            'sortDir'        => $crudData['sortDir'] ?? null,
        ];
    }

    /**
     * Render full page response.
     */
    public function renderPage(ThemeInterface $theme, array $data): ResponseInterface
    {
        $html = $theme->renderList($data);

        $response = service('response');
        $response->setContentType('text/html');
        $response->setBody($this->wrapInPage($html, $theme));

        return $response;
    }

    /**
     * Wrap content in a full HTML page (for non-AJAX).
     */
    private function wrapInPage(string $content, ThemeInterface $theme): string
    {
        $cssLinks = '';
        foreach ($theme->getCssFiles() as $css) {
            $cssLinks .= '<link rel="stylesheet" href="' . $css . '">' . "\n";
        }

        $jsLinks = '';
        foreach ($theme->getJsFiles() as $js) {
            $jsLinks .= '<script src="' . $js . '"></script>' . "\n";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$this->config->defaultTheme}</title>
    {$cssLinks}
    <link rel="stylesheet" href="/assets/grocery-crud/css/grocery-crud.css">
</head>
<body>
    <div class="container-fluid py-4">
        {$content}
    </div>
    {$jsLinks}
    <script src="/assets/grocery-crud/js/grocery-crud.js"></script>
</body>
</html>
HTML;
    }
}
