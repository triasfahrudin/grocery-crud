<?php
/**
 * Print View Template for Grocery CRUD.
 *
 * Variables passed to this view:
 *   $subject      - string - Title of the CRUD
 *   $columns      - array  - Column names
 *   $columnLabels - array  - Display labels for columns
 *   $records      - array  - Data rows
 *   $totalCount   - int    - Number of records
 *   $exportFormat - string - 'print' or 'pdf'
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($subject) ?> - Print View</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        font-size: 12px;
        color: #1a202c;
        background: #fff;
        padding: 20px;
    }

    .no-print { display: block; }

    .print-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e2e8f0;
    }

    .print-header h1 {
        font-size: 20px;
        font-weight: 700;
        color: #1a202c;
    }

    .print-header .meta {
        font-size: 11px;
        color: #718096;
    }

    .print-header .meta span {
        display: block;
        text-align: right;
    }

    .toolbar {
        margin-bottom: 20px;
        display: flex;
        gap: 8px;
    }

    .toolbar button {
        padding: 8px 16px;
        border: 1px solid #cbd5e0;
        border-radius: 6px;
        background: #fff;
        color: #4a5568;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
    }

    .toolbar button:hover {
        background: #f7fafc;
        border-color: #a0aec0;
    }

    .toolbar .btn-primary {
        background: #3182ce;
        border-color: #3182ce;
        color: #fff;
    }

    .toolbar .btn-primary:hover {
        background: #2b6cb0;
    }

    .toolbar .btn-success {
        background: #38a169;
        border-color: #38a169;
        color: #fff;
    }

    .toolbar .btn-success:hover {
        background: #2f855a;
    }

    .info-bar {
        margin-bottom: 15px;
        font-size: 12px;
        color: #718096;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }

    thead th {
        background: #4a5568;
        color: #fff;
        padding: 8px 10px;
        text-align: left;
        font-weight: 600;
        font-size: 11px;
        white-space: nowrap;
    }

    tbody td {
        padding: 6px 10px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }

    tbody tr:nth-child(even) td {
        background: #f7fafc;
    }

    tbody tr:hover td {
        background: #edf2f7;
    }

    .footer {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
        font-size: 10px;
        color: #a0aec0;
        text-align: center;
    }

    @media print {
        .no-print { display: none !important; }

        body {
            padding: 0;
            font-size: 10px;
        }

        thead th {
            background: #4a5568 !important;
            color: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        tbody tr:nth-child(even) td {
            background: #f7fafc !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .print-header {
            margin-bottom: 10px;
            padding-bottom: 8px;
        }

        .print-header h1 { font-size: 16px; }

        .info-bar { margin-bottom: 10px; }

        @page {
            margin: 15mm 10mm;
        }
    }
</style>
</head>
<body>
    <div class="no-print toolbar">
        <button class="btn-primary" onclick="window.print()">
            &#128424; Print
        </button>
        <button onclick="window.close()">
            &#10005; Close
        </button>
    </div>

    <div class="print-header">
        <h1><?= htmlspecialchars($subject) ?></h1>
        <div class="meta">
            <span>Generated: <?= date('Y-m-d H:i:s') ?></span>
            <span>Total Records: <?= number_format((int) $totalCount) ?></span>
        </div>
    </div>

    <div class="info-bar">
        Showing all <?= number_format((int) $totalCount) ?> records.
    </div>

    <?php if (empty($records)): ?>
        <p style="color: #a0aec0; text-align: center; padding: 40px;">No records found.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <th><?= htmlspecialchars($columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col))) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <td><?php
                                    $val = $row[$col] ?? '';
                                    $val = is_array($val) ? '' : (string) $val;
                                    echo htmlspecialchars(strip_tags($val));
                                ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="footer">
        Grocery CRUD - Generated <?= date('Y-m-d H:i:s') ?>
    </div>

    <script>
        // Auto-print when page loads (for print action)
        <?php if (($exportFormat ?? 'print') === 'print'): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        <?php endif; ?>
    </script>
</body>
</html>
