<?php

declare(strict_types=1);

namespace GroceryCrud\Export;

class CsvExport
{
    /**
     * Mengekspor data sebagai string CSV.
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string, string>            $columnLabels
     * @param array<int, string>               $columns
     * @param string                           $delimiter
     * @return string
     */
    public function export(
        array $data,
        array $columnLabels,
        array $columns,
        string $delimiter = ','
    ): string {
        // Buka stream output
        $output = fopen('php://temp', 'r+');

        // Tulis header
        $header = [];
        foreach ($columns as $col) {
            $header[] = $columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
        }
        fputcsv($output, $header, $delimiter);

        // Tulis data
        foreach ($data as $row) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = $row[$col] ?? '';
            }
            fputcsv($output, $line, $delimiter);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Mendapatkan nama file CSV.
     */
    public function getFilename(string $table): string
    {
        return $table . '_' . date('Y-m-d_H-i-s') . '.csv';
    }

    /**
     * Mendapatkan header Content-Type untuk CSV.
     */
    public function getContentType(): string
    {
        return 'text/csv; charset=utf-8';
    }
}
