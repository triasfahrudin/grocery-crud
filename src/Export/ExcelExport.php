<?php

declare(strict_types=1);

namespace GroceryCrud\Export;

class ExcelExport
{
    /**
     * Mengekspor data sebagai tabel HTML dasar (kompatibel dengan Excel).
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string, string>            $columnLabels
     * @param array<int, string>               $columns
     * @return string
     */
    public function export(array $data, array $columnLabels, array $columns): string
    {
        $html = '<table>';
        $html .= '<thead><tr>';

        foreach ($columns as $col) {
            $html .= '<th>' . htmlspecialchars($columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col))) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $html .= '<td>' . htmlspecialchars((string) ($row[$col] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Mendapatkan nama file Excel.
     */
    public function getFilename(string $table): string
    {
        return $table . '_' . date('Y-m-d_H-i-s') . '.xls';
    }

    /**
     * Mendapatkan header Content-Type untuk Excel.
     */
    public function getContentType(): string
    {
        return 'application/vnd.ms-excel';
    }
}
