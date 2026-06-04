<?php

declare(strict_types=1);

namespace GroceryCrud\Export;

class CsvExport
{
    /**
     * Export data as CSV string.
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
        // Open output stream
        $output = fopen('php://temp', 'r+');

        // Write header
        $header = [];
        foreach ($columns as $col) {
            $header[] = $columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
        }
        fputcsv($output, $header, $delimiter);

        // Write data
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
     * Get CSV filename.
     */
    public function getFilename(string $table): string
    {
        return $table . '_' . date('Y-m-d_H-i-s') . '.csv';
    }

    /**
     * Get the Content-Type header for CSV.
     */
    public function getContentType(): string
    {
        return 'text/csv; charset=utf-8';
    }
}
