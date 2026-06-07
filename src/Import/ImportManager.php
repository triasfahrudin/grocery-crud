<?php

declare(strict_types=1);

namespace GroceryCrud\Import;

use GroceryCrud\Config\Config as GCConfig;
use GroceryCrud\Exceptions\GroceryCrudException;

class ImportManager
{
    private GCConfig $config;

    private const MAX_PREVIEW_ROWS = 5;

    public function __construct(GCConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Parse an uploaded CSV/XLSX file.
     *
     * @param array $file $_FILES entry
     * @return array{headers: string[], preview: array[], totalRows: int, filename: string}
     */
    public function parse(array $file): array
    {
        $this->validateFile($file);

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->parseCsv($file['tmp_name']),
            'xlsx' => $this->parseXlsx($file['tmp_name']),
            default => throw GroceryCrudException::invalidFileType(),
        };
    }

    /**
     * Detect best field match for each import column header.
     *
     * @param string[] $headers Import column headers
     * @param string[] $fields Available form fields
     * @param string[] $columnLabels Label => field mapping
     * @return array<int, string|null> header-index => field-name (or null)
     */
    public function detectMapping(array $headers, array $fields, array $columnLabels): array
    {
        $mapping = [];

        // Build lookup: lower(label) => field, lower(field) => field
        $labelLookup = [];
        foreach ($columnLabels as $field => $label) {
            $labelLookup[$this->normalize($label)] = $field;
        }
        $fieldLookup = [];
        foreach ($fields as $field) {
            $fieldLookup[$this->normalize($field)] = $field;
        }

        foreach ($headers as $header) {
            $normalized = $this->normalize($header);
            // Try exact label match first
            if (isset($labelLookup[$normalized])) {
                $mapping[] = $labelLookup[$normalized];
            } elseif (isset($fieldLookup[$normalized])) {
                $mapping[] = $fieldLookup[$normalized];
            } else {
                $mapping[] = null; // Not mapped
            }
        }

        return $mapping;
    }

    /**
     * Execute import: insert rows into the model.
     *
     * @param array<int, array<string, string>> $rows Data rows (field => value)
     * @param callable $insertFn function(array $data): mixed Returns insertId
     * @return array{imported: int, errors: array}
     */
    public function execute(array $rows, callable $insertFn): array
    {
        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $result = $insertFn($row);
                if ($result !== false && $result !== 0) {
                    $imported++;
                } else {
                    $errors[] = [
                        'row'     => $index + 2, // +2 for header + 0-index
                        'message' => 'Failed to insert row.',
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = [
                    'row'     => $index + 2,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'imported' => $imported,
            'errors'   => $errors,
        ];
    }

    private function validateFile(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw GroceryCrudException::uploadError();
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            throw GroceryCrudException::invalidFileType();
        }

        $maxSize = ($this->config->importConfig['maxSize'] ?? 2048) * 1024;
        if ($file['size'] > $maxSize) {
            throw GroceryCrudException::fileTooLarge();
        }
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open CSV file.');
        }

        // Detect BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Read headers
        $headers = fgetcsv($handle);
        if ($headers === false || $headers === null) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or has no headers.');
        }

        $headers = array_map('trim', $headers);

        // Read data rows
        $allRows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $allRows[] = $row;
        }
        fclose($handle);

        $totalRows = count($allRows);

        // Map rows to associative arrays
        $mapped = [];
        foreach ($allRows as $rowIndex => $row) {
            $assoc = [];
            foreach ($headers as $colIndex => $header) {
                $assoc[$header] = $row[$colIndex] ?? '';
            }
            $mapped[] = $assoc;
        }

        // Preview: first N rows as raw arrays
        $preview = array_slice($mapped, 0, self::MAX_PREVIEW_ROWS);

        return [
            'headers'   => $headers,
            'preview'   => $preview,
            'totalRows' => $totalRows,
            'filename'  => basename($path),
        ];
    }

    private function parseXlsx(string $path): array
    {
        // Check if PhpSpreadsheet is available
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new \RuntimeException(
                'Excel import requires phpoffice/phpspreadsheet. Install it with: composer require phpoffice/phpspreadsheet'
            );
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();

        if (empty($data)) {
            throw new \RuntimeException('Excel file is empty.');
        }

        // First row = headers
        $headers = array_map('trim', array_map('strval', $data[0]));
        $totalRows = count($data) - 1;

        // Preview: rows 1..N (after header)
        $previewRows = array_slice($data, 1, self::MAX_PREVIEW_ROWS);
        $preview = [];
        foreach ($previewRows as $row) {
            $assoc = [];
            foreach ($headers as $colIndex => $header) {
                $assoc[$header] = $row[$colIndex] ?? '';
            }
            $preview[] = $assoc;
        }

        return [
            'headers'   => $headers,
            'preview'   => $preview,
            'totalRows' => $totalRows,
            'filename'  => basename($path),
        ];
    }

    private function normalize(string $str): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $str)));
    }
}
