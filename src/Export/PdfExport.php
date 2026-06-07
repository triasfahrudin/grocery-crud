<?php

declare(strict_types=1);

namespace GroceryCrud\Export;

class PdfExport
{
    /**
     * Export data as PDF string.
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string, string>            $columnLabels
     * @param array<int, string>               $columns
     * @param string                           $title
     * @return string PDF binary content
     * @throws \RuntimeException If DomPDF is not installed
     */
    public function export(
        array $data,
        array $columnLabels,
        array $columns,
        string $title = ''
    ): string {
        if (!class_exists('\Dompdf\Dompdf')) {
            throw new \RuntimeException(
                'DomPDF library is required for PDF export. '
                . 'Install it via: composer require dompdf/dompdf'
            );
        }

        $html = $this->buildHtml($data, $columnLabels, $columns, $title);

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultMediaType', 'print');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Get PDF filename.
     */
    public function getFilename(string $table): string
    {
        return $table . '_' . date('Y-m-d_H-i-s') . '.pdf';
    }

    /**
     * Get the Content-Type header for PDF.
     */
    public function getContentType(): string
    {
        return 'application/pdf';
    }

    /**
     * Build HTML table for PDF output.
     */
    public function buildHtml(array $data, array $columnLabels, array $columns, string $title = ''): string
    {
        $subject = htmlspecialchars($title ?: 'Export');
        $date    = date('Y-m-d H:i:s');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{$subject}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; margin: 20px; }
    h2 { text-align: center; margin-bottom: 5px; font-size: 14pt; }
    .date { text-align: center; color: #666; font-size: 9pt; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #4a5568; color: white; padding: 6px 8px; text-align: left; font-size: 9pt; }
    td { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9pt; }
    tr:nth-child(even) td { background: #f7fafc; }
    th, td { border: 1px solid #cbd5e0; }
</style>
</head>
<body>
    <h2>{$subject}</h2>
    <div class="date">Generated: {$date}</div>
    <table>
        <thead><tr>
HTML;

        // Header row
        foreach ($columns as $col) {
            $label = htmlspecialchars($columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col)));
            $html .= '<th>' . $label . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        // Data rows
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                $value = is_array($value) ? '' : (string) $value;
                $html .= '<td>' . htmlspecialchars(strip_tags($value)) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</body></html>';

        return $html;
    }
}
