<?php

declare(strict_types=1);

namespace GroceryCrud\Themes;

class AdminLTE4Theme implements ThemeInterface
{
    /** @var array<string, string> */
    private array $languageStrings = [];

    /**
     * Mengatur string bahasa untuk tema.
     *
     * @param array<string, string> $strings
     */
    public function setLanguageStrings(array $strings): void
    {
        $this->languageStrings = $strings;
    }

    public function getName(): string
    {
        return 'adminlte4';
    }

    public function getCssFiles(): array
    {
        return [
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
            'https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css',
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
            'https://cdn.jsdelivr.net/npm/@fontsource/source-sans-pro@5/css/all.min.css',
            'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css',
        ];
    }

    public function getJsFiles(): array
    {
        return [
            'https://code.jquery.com/jquery-3.7.1.min.js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
            'https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/js/adminlte.min.js',
            'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.min.js',
        ];
    }

    public function renderList(array $data): string
    {
        $lang = $this->languageStrings;

        $columns      = $data['columns'] ?? [];
        $columnLabels = $data['columnLabels'] ?? [];
        $records      = $data['records'] ?? [];
        $pager        = $data['pager'] ?? null;
        $totalCount   = (int) ($data['totalCount'] ?? 0);
        $perPage      = (int) ($data['perPage'] ?? 25);
        $currentPage  = (int) ($data['currentPage'] ?? 1);
        $subject      = $data['subject'] ?? 'Records';
        $actions      = $data['actions'] ?? ['add', 'edit', 'delete'];
        $hasEdit      = in_array('edit', $actions, true);
        $hasDelete    = in_array('delete', $actions, true);
        $hasAdd       = in_array('add', $actions, true);
        $primaryKey   = $data['primaryKey'] ?? 'id';
        $showActions  = $hasEdit || $hasDelete;
        $customActions = $data['customActions'] ?? [];
        $useDatatables = $data['useDatatables'] ?? false;
        $crudId       = $data['crudId'] ?? 'crud_' . uniqid();
        $searchable   = (bool) ($data['searchable'] ?? false);
        $sortField    = $data['sortField'] ?? null;
        $sortDir      = $data['sortDir'] ?? 'ASC';
        $exportFormats = $data['exportFormats'] ?? [];
        $enableExport = (bool) ($data['enableExport'] ?? false);
        $enableImport = (bool) ($data['enableImport'] ?? false);

        $columnFilters = $data['columnFilters'] ?? [];
        $currentFilters = $data['currentFilters'] ?? [];
        $batchActions  = $data['batchActions'] ?? [];
        $hasBatch      = !empty($batchActions);
        $enableFilters  = (bool) ($data['enableFilters'] ?? true);
        $enableColumns  = (bool) ($data['enableColumns'] ?? true);
        $enableSettings = (bool) ($data['enableSettings'] ?? true);

        $totalPages   = $perPage > 0 ? (int) ceil($totalCount / $perPage) : 1;
        $colspan      = count($columns) + ($showActions ? 1 : 0) + (count($customActions) > 0 ? count($customActions) : 0) + ($hasBatch ? 1 : 0);

        // Pre-resolve language strings
        $lblExport      = $lang['export'] ?? 'Export';
        $lblExportCsv   = $lang['export_csv'] ?? 'Export CSV';
        $lblExportExcel = $lang['export_excel'] ?? 'Export Excel';
        $lblExportPdf   = $lang['export_pdf'] ?? 'Export PDF';
        $lblPrintView   = $lang['print_view'] ?? 'Print View';
        $lblAddRecord    = $lang['add_record'] ?? 'Add Record';
        $lblSearch       = $lang['search'] ?? 'Search';
        $lblActions      = $lang['actions'] ?? 'Actions';
        $lblNoRecords    = $lang['no_records'] ?? 'No records found.';
        $lblEdit         = $lang['edit'] ?? 'Edit';
        $lblDelete       = $lang['delete'] ?? 'Delete';
        $lblOf           = $lang['of'] ?? 'of';
        $lblRecords      = $lang['records'] ?? 'records';
        $lblPrevious     = $lang['previous'] ?? 'Previous';
        $lblNext         = $lang['next'] ?? 'Next';
        $lblBatchDelete  = $lang['batch_delete'] ?? 'Delete Selected';
        $lblSelectAll    = $lang['select_all'] ?? 'Select All';
        $lblFilter       = $lang['filter'] ?? 'Filter';
        $lblAll          = $lang['all'] ?? 'All';

        // Header
        $html = '<div class="grocery-crud-wrapper" id="' . $crudId . '">';
        $html .= '<div class="card card-primary card-outline">';
        $html .= '<div class="card-header d-flex justify-content-between align-items-center">';
        $html .= '<h3 class="card-title"><i class="bi bi-table me-2"></i>' . $subject . '</h3>';
        $html .= '<div class="card-tools d-flex gap-2">';

        // Import button
        if ($enableImport) {
            $html .= '<button type="button" class="btn btn-tool btn-gc-import" title="' . ($lang['import'] ?? 'Import') . '">';
            $html .= '<i class="bi bi-upload"></i>';
            $html .= '</button>';
        }

        // Export buttons
        if ($enableExport && !empty($exportFormats)) {
            $html .= '<div class="dropdown me-2">';
            $html .= '<button class="btn btn-tool dropdown-toggle" type="button" data-bs-toggle="dropdown">';
            $html .= '<i class="bi bi-download"></i>';
            $html .= '</button>';
            $html .= '<ul class="dropdown-menu dropdown-menu-right">';
            foreach ($exportFormats as $format) {
                $label = match ($format) {
                    'csv'       => $lblExportCsv,
                    'excel'     => $lblExportExcel,
                    'pdf'       => $lblExportPdf,
                    'print'     => $lblPrintView,
                    default     => ucfirst($format),
                };
                $html .= '<li><a class="dropdown-item" href="#" data-export="' . $format . '">' . $label . '</a></li>';
            }
            $html .= '</ul></div>';
        }

        // Filter button
        if ($enableFilters) {
            $html .= '<button type="button" class="btn btn-tool gc-filter-btn" title="' . ($lang['filters'] ?? 'Filters') . '">';
            $html .= '<i class="bi bi-funnel"></i>';
            $html .= '</button>';
        }

        // Columns button
        if ($enableColumns) {
            $html .= '<div class="dropdown">';
            $html .= '<button type="button" class="btn btn-tool dropdown-toggle gc-columns-btn" title="' . ($lang['columns'] ?? 'Columns') . '" data-bs-toggle="dropdown" aria-expanded="false">';
            $html .= '<i class="bi bi-layout-three-columns"></i>';
            $html .= '</button>';
            $html .= '<div class="dropdown-menu dropdown-menu-right gc-columns-menu p-2" style="min-width:200px"></div>';
            $html .= '</div>';
        }

        // Settings button
        if ($enableSettings) {
            $html .= '<div class="dropdown">';
            $html .= '<button type="button" class="btn btn-tool dropdown-toggle gc-tool-btn" title="' . ($lang['settings'] ?? 'Settings') . '" data-bs-toggle="dropdown" aria-expanded="false">';
            $html .= '<i class="bi bi-gear"></i>';
            $html .= '</button>';
            $html .= '<ul class="dropdown-menu dropdown-menu-right gc-settings-menu">';
            $html .= '<li><a class="dropdown-item gc-settings-save" href="#"><i class="bi bi-floppy me-2"></i>' . ($lang['save_settings'] ?? 'Save') . '</a></li>';
            $html .= '<li><a class="dropdown-item gc-settings-load" href="#"><i class="bi bi-arrow-counterclockwise me-2"></i>' . ($lang['load_settings'] ?? 'Load') . '</a></li>';
            $html .= '<li><hr class="dropdown-divider"></li>';
            $html .= '<li><a class="dropdown-item gc-settings-reset" href="#"><i class="bi bi-trash me-2"></i>' . ($lang['reset_settings'] ?? 'Reset') . '</a></li>';
            $html .= '</ul>';
            $html .= '</div>';
        }

        // Add button
        if ($hasAdd) {
            $html .= '<button type="button" class="btn btn-primary btn-sm btn-gc-add">';
            $html .= '<i class="bi bi-plus-lg me-1"></i>' . $lblAddRecord;
            $html .= '</button>';
        }

        $html .= '</div></div>';
        $html .= '<div class="card-body">';

        // Filter panel (hidden by default)
        if ($enableFilters) {
            $html .= '<div class="gc-filter-panel mb-3 p-3 bg-light border rounded" style="display:none">';
            $html .= '<div class="gc-filter-rows">';
            // Template row (hidden, cloned by JS)
            $html .= '<div class="gc-filter-item gc-filter-item-template" style="display:none">';
            $html .= '<select class="form-select form-select-sm gc-filter-col" style="min-width:130px"><option value="">' . ($lang['select_column'] ?? 'Column') . '</option></select>';
            $html .= '<select class="form-select form-select-sm gc-filter-op" style="min-width:110px">';
            $html .= '<option value="contains">' . ($lang['contains'] ?? 'Contains') . '</option>';
            $html .= '<option value="equals">' . ($lang['equals'] ?? 'Equals') . '</option>';
            $html .= '<option value="not_equal">' . ($lang['not_equal'] ?? 'Not equal') . '</option>';
            $html .= '<option value="starts_with">' . ($lang['starts_with'] ?? 'Starts with') . '</option>';
            $html .= '<option value="ends_with">' . ($lang['ends_with'] ?? 'Ends with') . '</option>';
            $html .= '<option value="greater_than">' . ($lang['greater_than'] ?? 'Greater than') . '</option>';
            $html .= '<option value="less_than">' . ($lang['less_than'] ?? 'Less than') . '</option>';
            $html .= '</select>';
            $html .= '<input type="text" class="form-control form-control-sm gc-filter-val" placeholder="' . ($lang['value'] ?? 'Value') . '" style="min-width:150px">';
            $html .= '<button type="button" class="gc-filter-item-remove" title="' . ($lang['remove'] ?? 'Remove') . '">&times;</button>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="gc-filter-actions">';
            $html .= '<button type="button" class="btn btn-sm btn-outline-primary gc-filter-add">+ ' . ($lang['add_filter'] ?? 'Add Filter') . '</button>';
            $html .= ' <button type="button" class="btn btn-sm btn-primary gc-filter-apply">' . ($lang['apply'] ?? 'Apply') . '</button>';
            $html .= ' <button type="button" class="btn btn-sm btn-outline-secondary gc-filter-clear">' . ($lang['clear'] ?? 'Clear') . '</button>';
            $html .= '</div>';
            $html .= '</div>';
        }

        // Search bar + batch toolbar
        $html .= '<div class="row mb-3 align-items-center">';
        if ($searchable) {
            $html .= '<div class="col-md-6">';
            $html .= '<div class="input-group input-group-sm">';
            $html .= '<input type="text" class="form-control gc-search-input" placeholder="' . $lblSearch . '...">';
            $html .= '<button class="btn btn-outline-secondary gc-search-clear" type="button" style="display:none" tabindex="-1"><i class="bi bi-x-lg"></i></button>';
            $html .= '<span class="input-group-text"><i class="bi bi-search"></i></span>';
            $html .= '</div></div>';
        } else {
            $html .= '<div class="col-md-6"></div>';
        }

        // Batch action toolbar
        if ($hasBatch) {
            $html .= '<div class="col-md-6 text-end">';
            $html .= '<div class="gc-batch-toolbar" style="display:none">';
            $html .= '<span class="gc-selected-count badge bg-secondary me-2"><span class="gc-selected-num">0</span> ' . $lblRecords . '</span>';
            foreach ($batchActions as $actionId => $label) {
                $extraClass = match ($actionId) {
                    'delete_selected'  => ' btn-danger',
                    'restore_selected' => ' btn-success',
                    default            => ' btn-outline-secondary',
                };
                $html .= '<button type="button" class="btn btn-sm' . $extraClass . ' gc-batch-action" data-batch-action="' . $actionId . '">' . htmlspecialchars($label) . '</button>';
            }
            $html .= '</div></div>';
        }

        $html .= '</div>';

        // Table
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-hover table-bordered align-middle mb-0 gc-table" data-crud-id="' . $crudId . '">';
        $html .= '<thead>';

        // Header row
        $html .= '<tr>';
        if ($hasBatch) {
            $html .= '<th class="text-center" style="width:40px"><input type="checkbox" class="gc-select-all" title="' . $lblSelectAll . '"></th>';
        }
        foreach ($columns as $col) {
            $label = $columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
            $isSorted = $col === $sortField;
            $dir = $isSorted ? $sortDir : 'ASC';
            $nextDir = $isSorted && $dir === 'ASC' ? 'DESC' : 'ASC';
            $arrow = $isSorted ? ($dir === 'ASC' ? ' &#9650;' : ' &#9660;') : '';
            $html .= '<th class="text-nowrap gc-sortable" data-column="' . $col . '" data-label="' . htmlspecialchars($label) . '" data-sort-field="' . $col . '" data-sort-dir="' . $nextDir . '">' . htmlspecialchars($label) . $arrow . '</th>';
        }

        if ($showActions || !empty($customActions)) {
            $html .= '<th class="text-center text-nowrap" style="width:120px">' . $lblActions . '</th>';
        }

        $html .= '</tr>';

        // Filter row (inline per-column) — hidden when new filter panel is active
        if (!empty($columnFilters)) {
            $html .= '<tr class="gc-filter-row">';
            if ($hasBatch) {
                $html .= '<td></td>';
            }
            foreach ($columns as $col) {
                $html .= '<td data-column="' . $col . '">';
                if (isset($columnFilters[$col])) {
                    $filterDef = $columnFilters[$col];
                    $filterType = $filterDef['type'] ?? 'text';
                    $filterOptions = $filterDef['options'] ?? [];
                    $currentVal = $currentFilters[$col] ?? '';
                    if ($filterType === 'dropdown') {
                        $html .= '<select class="form-select form-select-sm gc-column-filter" data-filter-field="' . $col . '">';
                        $html .= '<option value="">' . $lblAll . '</option>';
                        foreach ($filterOptions as $optValue => $optLabel) {
                            $selected = (string) $currentVal === (string) $optValue ? ' selected' : '';
                            $html .= '<option value="' . htmlspecialchars((string) $optValue) . '"' . $selected . '>' . htmlspecialchars((string) $optLabel) . '</option>';
                        }
                        $html .= '</select>';
                    } else {
                        $html .= '<input type="text" class="form-control form-control-sm gc-column-filter" data-filter-field="' . $col . '" placeholder="' . $lblFilter . '" value="' . htmlspecialchars((string) $currentVal) . '">';
                    }
                }
                $html .= '</td>';
            }
            if ($showActions || !empty($customActions)) {
                $html .= '<td></td>';
            }
            $html .= '</tr>';
        }

        $html .= '</thead><tbody>';

        if (empty($records)) {
            $colspanDisplay = $colspan;
            $html .= '<tr><td colspan="' . $colspanDisplay . '" class="text-center text-muted py-4">' . $lblNoRecords . '</td></tr>';
        } else {
            foreach ($records as $row) {
                $rowId = htmlspecialchars((string) ($row[$primaryKey] ?? ''));
                $html .= '<tr>';
                if ($hasBatch) {
                    $html .= '<td class="text-center"><input type="checkbox" class="gc-row-checkbox" value="' . $rowId . '"></td>';
                }
                foreach ($columns as $col) {
                    $value = $row[$col] ?? '';
                    $html .= '<td data-column="' . $col . '">' . $value . '</td>';
                }

                if ($showActions || !empty($customActions)) {
                    $html .= '<td class="text-center text-nowrap">';
                    $html .= '<div class="btn-group btn-group-sm">';

                    if ($hasEdit) {
                        $rowId = htmlspecialchars((string) ($row[$primaryKey] ?? ''));
                        $html .= '<button type="button" class="btn btn-outline-primary btn-gc-edit" data-id="' . $rowId . '" title="' . $lblEdit . '">';
                        $html .= '<i class="bi bi-pencil"></i></button>';
                    }

                    foreach ($customActions as $action) {
                        $rowId = (string) ($row[$primaryKey] ?? '');
                        $url = str_replace('{id}', $rowId, $action['url'] ?? '#');
                        $actionLabel = $action['label'] ?? '';
                        $actionIcon = $action['icon'] ?? '';
                        $actionCss = $action['cssClass'] ?? '';
                        $actionClasses = 'btn btn-outline-secondary gc-custom-action';
                        if ($actionCss !== '') {
                            $actionClasses .= ' ' . htmlspecialchars($actionCss);
                        }
                        $html .= '<a href="' . $url . '" class="' . $actionClasses . '" title="' . htmlspecialchars($actionLabel) . '" data-id="' . $rowId . '">';
                        if ($actionIcon !== '') {
                            $html .= '<i class="' . htmlspecialchars($actionIcon) . '"></i>';
                        } else {
                            $html .= htmlspecialchars($actionLabel);
                        }
                        $html .= '</a>';
                    }

                    if ($hasDelete) {
                        $rowId = htmlspecialchars((string) ($row[$primaryKey] ?? ''));
                        $html .= '<button type="button" class="btn btn-outline-danger btn-gc-delete" data-id="' . $rowId . '" title="' . $lblDelete . '">';
                        $html .= '<i class="bi bi-trash"></i></button>';
                    }

                    $html .= '</div></td>';
                }

                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';

        // Pagination
        if ($totalPages > 1 && $pager !== null) {
            $from = $pager['from'] ?? 0;
            $to   = $pager['to'] ?? 0;

            $html .= '<div class="d-flex justify-content-between align-items-center mt-3">';
            $html .= '<div class="text-muted small">' . $from . '&ndash;' . $to . ' ' . $lblOf . ' ' . $totalCount . ' ' . $lblRecords . '</div>';
            $html .= '<nav><ul class="pagination pagination-sm mb-0">';

            // Previous
            $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
            $html .= '<li class="page-item' . $prevDisabled . '">';
            $html .= '<a class="page-link gc-page-link" href="#" data-page="' . ($currentPage - 1) . '">' . $lblPrevious . '</a></li>';

            // Page numbers
            $startPage = max(1, $currentPage - 2);
            $endPage   = min($totalPages, $currentPage + 2);

            if ($startPage > 1) {
                $html .= '<li class="page-item"><a class="page-link gc-page-link" href="#" data-page="1">1</a></li>';
                if ($startPage > 2) {
                    $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                }
            }

            for ($i = $startPage; $i <= $endPage; $i++) {
                $active = $i === $currentPage ? ' active' : '';
                $html .= '<li class="page-item' . $active . '"><a class="page-link gc-page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
            }

            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                }
                $html .= '<li class="page-item"><a class="page-link gc-page-link" href="#" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
            }

            // Next
            $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
            $html .= '<li class="page-item' . $nextDisabled . '">';
            $html .= '<a class="page-link gc-page-link" href="#" data-page="' . ($currentPage + 1) . '">' . $lblNext . '</a></li>';

            $html .= '</ul></nav></div>';
        }

        $html .= '</div></div></div>';

        return $html;
    }

    public function renderAddForm(array $data): string
    {
        return $this->renderForm('add', $data);
    }

    public function renderEditForm(array $data): string
    {
        return $this->renderForm('edit', $data);
    }

    /**
     * Render a form (add or edit).
     */
    private function renderForm(string $mode, array $data): string
    {
        $lang = $this->languageStrings;

        $fields      = $data['fields'] ?? [];
        $fieldLabels = $data['fieldLabels'] ?? [];
        $fieldTypes  = $data['fieldTypes'] ?? [];
        $fieldValues = $data['fieldValues'] ?? [];
        $fieldOptions = $data['fieldOptions'] ?? [];
        $dependsOn   = $data['dependsOn'] ?? [];
        $primaryKey  = $data['primaryKey'] ?? 'id';
        $recordId    = $data['recordId'] ?? null;
        $errors      = $data['errors'] ?? [];
        $subject     = $data['subject'] ?? 'Records';
        $crudId      = $data['crudId'] ?? 'crud_' . uniqid();

        $isEdit   = $mode === 'edit';
        $lblTitle = $isEdit
            ? ($lang['edit_record'] ?? 'Edit Record')
            : ($lang['add_record'] ?? 'Add Record');
        $lblSave   = $lang['save'] ?? 'Save';
        $lblCancel = $lang['cancel'] ?? 'Cancel';

        $modalIcon = $isEdit ? 'bi-pencil' : 'bi-plus-lg';

        $html = '<div class="grocery-crud-form-wrapper" id="' . $crudId . '_form">';
        $html .= '<div class="card card-primary">';
        $html .= '<div class="card-header d-flex justify-content-between align-items-center">';
        $html .= '<h4 class="card-title"><i class="bi ' . $modalIcon . ' me-2"></i>' . $lblTitle . '</h4>';
        $html .= '<button type="button" class="btn-close gc-form-close" aria-label="' . $lblCancel . '"></button>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        $html .= '<form class="gc-form" method="post" enctype="multipart/form-data" data-mode="' . $mode . '" data-crud-id="' . $crudId . '">';

        if ($isEdit && $recordId !== null) {
            $html .= '<input type="hidden" name="' . $primaryKey . '" value="' . htmlspecialchars((string) $recordId) . '">';
        }

        foreach ($fields as $field) {
            $label      = $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $value      = $fieldValues[$field] ?? '';
            $type       = $fieldTypes[$field] ?? 'text';
            $fieldError = $errors[$field] ?? '';
            $isRequired = !empty($data['requiredFields'][$field]);
            $isReadonly = in_array($field, $data['readOnlyFields'] ?? [], true);
            $options    = $fieldOptions[$field] ?? [];
            $isUpload   = !empty($data['uploadFields'][$field]);

            $depAttrs = '';
            if (isset($dependsOn[$field])) {
                $depAttrs = ' data-depends-on=\'' . htmlspecialchars(json_encode($dependsOn[$field])) . '\'';
            }
            $html .= '<div class="mb-3' . ($fieldError ? ' has-error' : '') . '"' . $depAttrs . '>';
            $html .= '<label for="gc_field_' . $field . '" class="form-label">';
            $html .= htmlspecialchars($label);
            if ($isRequired) {
                $html .= ' <span class="text-danger">*</span>';
            }
            $html .= '</label>';

            $html .= $this->renderFormField($field, $type, $value, $options, $isReadonly, $isUpload, $data);

            if ($fieldError) {
                $html .= '<div class="invalid-feedback d-block">' . htmlspecialchars($fieldError) . '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</form>';
        $html .= '</div>';
        $html .= '<div class="card-footer d-flex gap-2">';
        $html .= '<button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>' . $lblSave . '</button>';
        $html .= '<button type="button" class="btn btn-secondary gc-form-close"><i class="bi bi-x-lg me-1"></i>' . $lblCancel . '</button>';
        $html .= '</div>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render a single form field input.
     */
    private function renderFormField(
        string $field,
        string $type,
        mixed $value,
        array $options,
        bool $isReadonly,
        bool $isUpload,
        array $data
    ): string {
        $readonlyAttr = $isReadonly ? ' readonly' : '';
        $fieldId      = 'gc_field_' . $field;
        $fieldName    = $field;

        // Repeater data
        $repeaterDefs  = $data['repeaterFields'] ?? [];
        $repeaterData  = $data['repeaterData'] ?? [];
        $rDef          = $repeaterDefs[$field] ?? null;
        $rValues       = $repeaterData[$field] ?? [];

        $html = '';

        switch ($type) {
            case 'textarea':
            case 'text areas':
                $html .= '<textarea class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" rows="4"' . $readonlyAttr . '>' . htmlspecialchars((string) $value) . '</textarea>';
                break;

            case 'hidden':
                $html .= '<input type="hidden" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '">';
                break;

            case 'integer':
            case 'numeric':
                $html .= '<input type="number" step="any" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;

            case 'date':
                $html .= '<input type="date" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;

            case 'datetime':
                $html .= '<input type="datetime-local" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;

            case 'time':
                $html .= '<input type="time" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;

            case 'email':
                $html .= '<input type="email" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;

            case 'url':
                $html .= '<input type="url" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;

            case 'phone':
                $html .= '<input type="tel" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;

            case 'color':
                $html .= '<input type="color" class="form-control form-control-color" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;

            case 'password':
                $html .= '<input type="password" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '"' . $readonlyAttr . '>';
                if (!empty($value)) {
                    $html .= '<small class="text-muted">Leave empty to keep current password.</small>';
                }
                break;

            case 'true_false':
            case 'boolean':
                $checked = !empty($value) ? ' checked' : '';
                $html .= '<div class="form-check form-switch">';
                $html .= '<input class="form-check-input" type="checkbox" id="' . $fieldId . '" name="' . $fieldName . '" value="1"' . $checked . $readonlyAttr . '>';
                $html .= '</div>';
                break;

            case 'dropdown':
            case 'enum':
            case 'relation':
                $html .= '<select class="form-select" id="' . $fieldId . '" name="' . $fieldName . '"' . ($isReadonly ? ' disabled' : '') . '>';
                $html .= '<option value="">-- Select --</option>';
                foreach ($options as $optValue => $optLabel) {
                    $selected = ((string) $optValue === (string) $value) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars((string) $optValue) . '"' . $selected . '>' . htmlspecialchars((string) $optLabel) . '</option>';
                }
                $html .= '</select>';
                if ($isReadonly) {
                    $html .= '<input type="hidden" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '">';
                }
                break;

            case 'set':
            case 'n_to_n':
                $html .= '<div class="border rounded p-2" style="max-height:200px;overflow-y:auto;">';
                if (!empty($options) && is_array($options)) {
                    $selectedValues = is_array($value) ? $value : explode(',', (string) $value);
                    foreach ($options as $optValue => $optLabel) {
                        $checked = in_array((string) $optValue, $selectedValues, true) ? ' checked' : '';
                        $optId = $fieldId . '_' . md5((string) $optValue);
                        $html .= '<div class="form-check">';
                        $html .= '<input class="form-check-input" type="checkbox" id="' . $optId . '" name="' . $fieldName . '[]" value="' . htmlspecialchars((string) $optValue) . '"' . $checked . ($isReadonly ? ' disabled' : '') . '>';
                        $html .= '<label class="form-check-label" for="' . $optId . '">' . htmlspecialchars((string) $optLabel) . '</label>';
                        $html .= '</div>';
                    }
                }
                $html .= '</div>';
                break;

            case 'image':
            case 'file':
                $html .= '<input type="file" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '"' . $readonlyAttr . '>';
                if (!empty($value)) {
                    $html .= '<div class="mt-2">';
                    if ($type === 'image') {
                        $html .= '<img src="' . htmlspecialchars((string) $value) . '" class="img-thumbnail" style="max-height:100px" alt="">';
                    } else {
                        $html .= '<a href="' . htmlspecialchars((string) $value) . '" target="_blank" class="btn btn-sm btn-outline-secondary">';
                        $html .= '<i class="bi bi-paperclip"></i> ' . basename((string) $value) . '</a>';
                    }
                    $html .= '</div>';
                    $html .= '<input type="hidden" name="' . $fieldName . '_existing" value="' . htmlspecialchars((string) $value) . '">';
                }
                break;

            case 'richtext':
                $html .= '<div class="gc-richtext-editor" id="' . $fieldId . '_editor">' . $value . '</div>';
                $html .= '<textarea class="d-none" id="' . $fieldId . '" name="' . $fieldName . '">' . htmlspecialchars((string) $value) . '</textarea>';
                break;

            case 'read_only':
                $html .= '<input type="text" class="form-control" id="' . $fieldId . '" value="' . htmlspecialchars((string) $value) . '" readonly disabled>';
                break;

            case 'repeater':
                if ($rDef === null) break;
                $repeatables = $rDef['repeatables'] ?? [];
                $html .= '<div class="gc-repeater-container border rounded p-3">';

                // Existing items
                foreach ($rValues as $rIndex => $rItem) {
                    $html .= '<div class="gc-repeater-item card card-body mb-2 p-3">';
                    $html .= '<div class="d-flex justify-content-end mb-1">';
                    $html .= '<button type="button" class="btn btn-sm btn-outline-danger gc-repeater-remove"><i class="bi bi-trash"></i></button>';
                    $html .= '</div>';
                    foreach ($repeatables as $subField) {
                        $sfName  = $subField['name'];
                        $sfLabel = $subField['label'] ?? ucfirst($sfName);
                        $sfType  = $subField['type'] ?? 'text';
                        $sfOpts  = $subField['options'] ?? [];
                        $sfValue = $rItem[$sfName] ?? '';
                        $inputName = $fieldName . '[' . $rIndex . '][' . $sfName . ']';
                        $inputId   = $fieldId . '_' . $rIndex . '_' . $sfName;
                        $html .= '<div class="mb-2">';
                        $html .= '<label for="' . $inputId . '" class="form-label small">' . htmlspecialchars($sfLabel) . '</label>';
                        $html .= $this->renderRepeaterSubField($inputName, $inputId, $sfType, $sfValue, $sfOpts);
                        $html .= '</div>';
                    }
                    $html .= '</div>';
                }

                // Template for JS cloning
                $html .= '<div class="gc-repeater-template" style="display:none">';
                $template = '';
                $template .= '<div class="gc-repeater-item card card-body mb-2 p-3">';
                $template .= '<div class="d-flex justify-content-end mb-1">';
                $template .= '<button type="button" class="btn btn-sm btn-outline-danger gc-repeater-remove"><i class="bi bi-trash"></i></button>';
                $template .= '</div>';
                foreach ($repeatables as $subField) {
                    $sfName  = $subField['name'];
                    $sfLabel = $subField['label'] ?? ucfirst($sfName);
                    $sfType  = $subField['type'] ?? 'text';
                    $sfOpts  = $subField['options'] ?? [];
                    $inputName = $fieldName . '[__INDEX__][' . $sfName . ']';
                    $inputId   = $fieldId . '__INDEX__' . $sfName;
                    $template .= '<div class="mb-2">';
                    $template .= '<label for="' . $inputId . '" class="form-label small">' . htmlspecialchars($sfLabel) . '</label>';
                    $template .= $this->renderRepeaterSubField($inputName, $inputId, $sfType, '', $sfOpts, true);
                    $template .= '</div>';
                }
                $template .= '</div>';
                $html .= $template;
                $html .= '</div>';

                // Add button
                $html .= '<button type="button" class="btn btn-sm btn-outline-primary mt-2 gc-repeater-add"><i class="bi bi-plus-lg"></i> Add Item</button>';
                $html .= '</div>';
                break;

            default: // text
                $html .= '<input type="text" class="form-control" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
        }

        return $html;
    }

    /**
     * Render a sub-field within a repeater group.
     */
    private function renderRepeaterSubField(string $name, string $id, string $type, mixed $value, array $options = [], bool $disabled = false): string
    {
        $d = $disabled ? ' disabled' : '';
        switch ($type) {
            case 'textarea':
                return '<textarea class="form-control form-control-sm" id="' . $id . '" name="' . $name . '" rows="2"' . $d . '>' . htmlspecialchars((string) $value) . '</textarea>';

            case 'integer':
            case 'numeric':
                return '<input type="number" step="any" class="form-control form-control-sm" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';

            case 'select':
            case 'dropdown':
                $html = '<select class="form-select form-select-sm" id="' . $id . '" name="' . $name . '"' . $d . '>';
                $html .= '<option value="">-- Select --</option>';
                foreach ($options as $optValue => $optLabel) {
                    $selected = ((string) $optValue === (string) $value) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars((string) $optValue) . '"' . $selected . '>' . htmlspecialchars((string) $optLabel) . '</option>';
                }
                $html .= '</select>';
                return $html;

            case 'boolean':
            case 'true_false':
                $checked = !empty($value) ? ' checked' : '';
                return '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="' . $id . '" name="' . $name . '" value="1"' . $checked . $d . '></div>';

            case 'hidden':
                return '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';

            default: // text, string
                return '<input type="text" class="form-control form-control-sm" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';
        }
    }

    /**
     * Render sub-grid (nested table) HTML.
     *
     * @param array<string, mixed> $config
     * @param array<int, array<string, mixed>> $records
     * @return string
     */
    public function renderSubGrid(array $config, array $records): string
    {
        $columns      = $config['columns'] ?? [];
        $columnLabels = $config['columnLabels'] ?? [];
        $relatedTable = $config['relatedTable'] ?? '';
        $recordCount  = count($records);

        $html = '<style>
            .gc-subgrid-inner { margin:8px 0; }
            .subgrid-header { display:flex; align-items:center; gap:10px; padding:8px 12px; background:#343a40; color:#fff; border-radius:6px 6px 0 0; }
            .subgrid-title { font-weight:600; font-size:0.95rem; display:flex; align-items:center; gap:6px; }
            .subgrid-count { margin-left:auto; font-size:0.8rem; background:rgba(255,255,255,0.15); padding:2px 10px; border-radius:10px; }
            .gc-subgrid-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #dee2e6; border-radius:0 0 6px 6px; overflow:hidden; }
            .gc-subgrid-table thead th { background:#f8f9fa; padding:8px 12px; font-size:0.85rem; font-weight:600; text-transform:uppercase; letter-spacing:0.03em; color:#495057; border-bottom:2px solid #dee2e6; }
            .gc-subgrid-table tbody td { padding:8px 12px; font-size:0.9rem; border-bottom:1px solid #f0f0f0; color:#333; }
            .gc-subgrid-table tbody tr:last-child td { border-bottom:none; }
            .gc-subgrid-table tbody tr:hover td { background:#f1f8ff; border-left:3px solid #0d6efd; }
            .gc-subgrid-empty { text-align:center; color:#999; font-style:italic; padding:20px !important; }
        </style>';
        $html .= '<div class="gc-subgrid-inner">';

        // Header with table name and record count
        $tableLabel = ucfirst(str_replace('_', ' ', $relatedTable));
        $html .= '<div class="subgrid-header">';
        $html .= '<span class="subgrid-title"><i class="bi bi-grid-3x3-gap-fill"></i>' . htmlspecialchars($tableLabel) . '</span>';
        $html .= '<span class="subgrid-count">' . $recordCount . ' data</span>';
        $html .= '</div>';

        // Table
        $html .= '<table class="gc-subgrid-table">';
        $html .= '<thead><tr>';
        foreach ($columns as $col) {
            $label = $columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
            $html .= '<th>' . htmlspecialchars($label) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        if (empty($records)) {
            $colspan = count($columns);
            $html .= '<tr><td colspan="' . $colspan . '" class="gc-subgrid-empty">Tidak ada data terkait.</td></tr>';
        } else {
            foreach ($records as $row) {
                $html .= '<tr>';
                foreach ($columns as $col) {
                    $value = $row[$col] ?? '';
                    $html .= '<td>' . htmlspecialchars((string) $value) . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }

    public function renderImportForm(array $data): string
    {
        // Reuse Bootstrap5's import form rendering for layout consistency
        $b5 = new Bootstrap5Theme();
        $b5->setLanguageStrings($this->languageStrings);
        return $b5->renderImportForm($data);
    }

    // ================================================================
    // File Manager Methods
    // ================================================================

    public function renderFileManager(
        array $data,
        array $tree,
        array $config,
        array $languageStrings = []
    ): string {
        $lang = !empty($languageStrings) ? $languageStrings : $this->languageStrings;

        $crudId      = $data['crudId'] ?? 'crud_' . uniqid();
        $subject     = $data['subject'] ?? 'File Manager';
        $currentPath = $data['currentPath'] ?? '';
        $writable    = (bool) ($data['writable'] ?? false);
        $breadcrumb  = $data['breadcrumb'] ?? [];

        $lblFileManager   = $lang['file_manager'] ?? 'File Manager';
        $lblNewFolder     = $lang['file_manager_new_folder'] ?? 'New Folder';
        $lblUpload        = $lang['file_manager_upload'] ?? 'Upload';
        $lblSearch        = $lang['file_manager_search'] ?? 'Search files...';
        $lblRefresh       = $lang['file_manager_refresh'] ?? 'Refresh';
        $lblNoFolders     = $lang['no_records'] ?? 'No records found.';
        $lblBack          = $lang['back'] ?? 'Back';

        $html = '<div class="gc-file-manager" id="' . $crudId . '_fm">';
        $html .= '<div class="card shadow-sm">';
        $html .= '<div class="card-header bg-white d-flex justify-content-between align-items-center py-3">';
        $html .= '<h5 class="mb-0 fw-bold"><i class="bi bi-folder2-open me-2"></i>' . $lblFileManager . '</h5>';
        $html .= '<button type="button" class="btn btn-outline-secondary btn-sm gc-fm-back-to-list">';
        $html .= '<i class="bi bi-arrow-left me-1"></i>' . $lblBack . '</button>';
        $html .= '</div>';
        $html .= '<div class="card-body p-0">';
        $html .= '<div class="gc-fm-layout d-flex">';

        // Sidebar: Folder Tree
        $html .= '<div class="gc-fm-sidebar border-end p-3" style="min-width:220px;max-width:260px;overflow-y:auto;">';
        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
        $html .= '<span class="fw-semibold small text-uppercase text-muted"><i class="bi bi-folder me-1"></i>' . ($lang['file_manager_folders'] ?? 'Folders') . '</span>';
        $html .= '<button type="button" class="btn btn-sm btn-outline-secondary gc-fm-refresh-tree" title="' . $lblRefresh . '"><i class="bi bi-arrow-clockwise"></i></button>';
        $html .= '</div>';
        $html .= '<ul class="gc-fm-tree list-unstyled mb-0">';
        $html .= '<li class="gc-fm-tree-item gc-fm-tree-root" data-path="">';
        $html .= '<a href="#" class="text-decoration-none d-flex align-items-center py-1 px-2 rounded' . ($currentPath === '' ? ' active' : '') . '">';
        $html .= '<i class="bi bi-folder2-open text-warning me-2"></i><span>' . htmlspecialchars($subject) . '</span>';
        $html .= '</a>';
        $html .= $this->renderFolderTree($tree, [], $currentPath, 1);
        $html .= '</li>';
        $html .= '</ul>';
        $html .= '</div>';

        // Main Content
        $html .= '<div class="gc-fm-content flex-grow-1 d-flex flex-column" style="min-width:0;">';

        // Breadcrumb
        $html .= '<div class="gc-fm-breadcrumb border-bottom px-3 py-2 bg-light d-flex align-items-center gap-1 small">';
        $html .= $this->renderBreadcrumb($breadcrumb, $lang);
        $html .= '</div>';

        // Toolbar
        $html .= '<div class="gc-fm-toolbar border-bottom px-3 py-2 d-flex align-items-center gap-2 flex-wrap">';
        if ($writable) {
            $html .= '<button type="button" class="btn btn-primary btn-sm gc-fm-new-folder"><i class="bi bi-folder-plus me-1"></i>' . $lblNewFolder . '</button>';
            $html .= '<button type="button" class="btn btn-success btn-sm gc-fm-upload-btn"><i class="bi bi-cloud-arrow-up me-1"></i>' . $lblUpload . '</button>';
            $html .= '<form class="gc-fm-upload-form d-none" method="post" enctype="multipart/form-data">';
            $accept = $data['allowedTypes'] ?? '*';
            if ($accept !== '*') {
                $exts = array_map(fn($ext) => '.' . trim($ext), explode('|', $accept));
                $accept = implode(',', $exts);
            }
            $html .= '<input type="file" class="gc-fm-upload-input" multiple accept="' . htmlspecialchars($accept) . '">';
            $html .= '</form>';
        }
        $html .= '<div class="ms-auto d-flex gap-2">';
        $html .= '<div class="input-group input-group-sm" style="max-width:220px;">';
        $html .= '<input type="text" class="form-control gc-fm-search" placeholder="' . $lblSearch . '">';
        $html .= '<button class="btn btn-outline-secondary gc-fm-search-btn" type="button"><i class="bi bi-search"></i></button>';
        $html .= '<button class="btn btn-outline-secondary gc-fm-search-clear" type="button" style="display:none"><i class="bi bi-x-lg"></i></button>';
        $html .= '</div>';
        $html .= '<button type="button" class="btn btn-outline-secondary btn-sm gc-fm-refresh" title="' . $lblRefresh . '"><i class="bi bi-arrow-clockwise"></i></button>';
        $html .= '</div>';
        $html .= '</div>';

        // File list
        $html .= '<div class="gc-fm-list-container p-3 flex-grow-1" style="overflow-y:auto;min-height:350px;">';
        $html .= $this->renderFileManagerList($data);
        $html .= '</div>';

        // Status bar
        $html .= '<div class="gc-fm-status border-top px-3 py-1 text-muted small d-flex justify-content-between">';
        $html .= '<span class="gc-fm-status-path" data-path="' . htmlspecialchars($currentPath) . '">' . ($lang['file_manager_current_dir'] ?? 'Current directory') . ': <strong>' . ($currentPath ?: '/') . '</strong></span>';
        $html .= '<span class="gc-fm-status-count"></span>';
        $html .= '</div>';

        $html .= '</div>'; // .gc-fm-content
        $html .= '</div>'; // .gc-fm-layout
        $html .= '</div>'; // .card-body
        $html .= '</div>'; // .card
        $html .= '</div>'; // .gc-file-manager

        return $html;
    }

    public function renderFileManagerList(
        array $data,
        array $languageStrings = []
    ): string {
        $lang = !empty($languageStrings) ? $languageStrings : $this->languageStrings;

        $folders     = $data['folders'] ?? [];
        $files       = $data['files'] ?? [];
        $currentPath = $data['currentPath'] ?? '';
        $writable    = (bool) ($data['writable'] ?? false);
        $parentPath  = $data['parentPath'] ?? null;

        $lblName       = $lang['file_manager_name'] ?? 'Name';
        $lblSize       = $lang['file_manager_size'] ?? 'Size';
        $lblModified   = $lang['file_manager_modified'] ?? 'Modified';
        $lblActions    = $lang['actions'] ?? 'Actions';
        $lblNoFiles    = $lang['file_manager_empty'] ?? 'This folder is empty.';
        $lblParent     = $lang['file_manager_parent'] ?? '.. (Parent)';

        $html = '';

        if ($parentPath !== null) {
            $html .= '<div class="gc-fm-item gc-fm-item-folder gc-fm-item-parent d-flex align-items-center px-3 py-2 border-bottom" data-path="' . htmlspecialchars($parentPath) . '">';
            $html .= '<i class="bi bi-arrow-return-left text-secondary me-3" style="font-size:1.2rem;"></i>';
            $html .= '<div class="flex-grow-1"><span class="fw-medium text-primary">' . $lblParent . '</span></div>';
            $html .= '<div class="text-muted small" style="min-width:80px;text-align:right;">-</div>';
            $html .= '<div class="text-muted small" style="min-width:150px;text-align:right;">-</div>';
            $html .= '<div style="min-width:100px;text-align:right;"></div>';
            $html .= '</div>';
        }

        // Header
        $html .= '<div class="gc-fm-item gc-fm-header d-flex align-items-center px-3 py-2 border-bottom bg-light fw-semibold small text-uppercase text-muted">';
        $html .= '<div class="gc-fm-col-icon" style="width:32px;"></div>';
        $html .= '<div class="flex-grow-1">' . $lblName . '</div>';
        $html .= '<div class="text-end" style="min-width:80px;">' . $lblSize . '</div>';
        $html .= '<div class="text-end" style="min-width:150px;">' . $lblModified . '</div>';
        $html .= '<div class="text-center" style="min-width:100px;">' . $lblActions . '</div>';
        $html .= '</div>';

        if (empty($folders) && empty($files)) {
            $html .= '<div class="text-center text-muted py-5"><i class="bi bi-folder2-open d-block mb-2" style="font-size:2.5rem;opacity:0.3;"></i>' . $lblNoFiles . '</div>';
        } else {
            foreach ($folders as $folder) {
                $folder['writable'] = $writable;
                $folder['isDir'] = true;
                $html .= $this->renderFileManagerItem($folder);
            }
            foreach ($files as $file) {
                $file['writable'] = $writable;
                $file['isDir'] = false;
                $html .= $this->renderFileManagerItem($file);
            }
        }

        return $html;
    }

    public function renderFileManagerItem(
        array $item,
        array $languageStrings = []
    ): string {
        $lang = !empty($languageStrings) ? $languageStrings : $this->languageStrings;
        $writable = (bool) ($item['writable'] ?? false);
        $isDir = (bool) ($item['isDir'] ?? false);
        $name = htmlspecialchars($item['name']);
        $path = htmlspecialchars($item['path']);
        $icon = $item['icon'] ?? ($isDir ? 'bi-folder' : 'bi-file-earmark');
        $size = htmlspecialchars($item['sizeHuman'] ?? '-');
        $modified = htmlspecialchars($item['modified'] ?? '-');
        $isImage = (bool) ($item['isImage'] ?? false);
        $url = htmlspecialchars($item['url'] ?? '');
        $ext = htmlspecialchars($item['ext'] ?? '');

        $iconColor = $isDir ? 'text-warning' : 'text-secondary';
        $itemClass = $isDir ? 'gc-fm-item-folder' : 'gc-fm-item-file';
        $clickable = $isDir ? ' gc-fm-item-clickable' : '';

        $lblRename  = $lang['file_manager_rename'] ?? 'Rename';
        $lblDelete  = $lang['file_manager_delete'] ?? 'Delete';
        $lblMove    = $lang['file_manager_move'] ?? 'Move';
        $lblCopy    = $lang['file_manager_copy'] ?? 'Copy';
        $lblDownload = $lang['file_manager_download'] ?? 'Download';
        $lblPreview = $lang['file_manager_preview'] ?? 'Preview';

        $html = '<div class="gc-fm-item d-flex align-items-center px-3 py-2 border-bottom ' . $itemClass . $clickable . '" data-path="' . $path . '" data-name="' . $name . '" data-isdir="' . ($isDir ? '1' : '0') . '" data-ext="' . $ext . '">';

        // Icon
        $html .= '<div class="gc-fm-col-icon text-center me-3 ' . $iconColor . '" style="width:24px;"><i class="bi ' . $icon . '"></i></div>';

        // Name
        $html .= '<div class="flex-grow-1 text-truncate">';
        if ($isDir) {
            $html .= '<a href="#" class="text-decoration-none text-body fw-medium gc-fm-folder-link">' . $name . '</a>';
        } else {
            if ($isImage) {
                $html .= '<a href="' . $url . '" class="text-decoration-none text-body gc-fm-image-link" target="_blank" data-url="' . $url . '">' . $name . '</a>';
            } else {
                $html .= '<span class="text-body">' . $name . '</span>';
            }
        }
        $html .= '</div>';

        // Size
        $html .= '<div class="text-muted small text-end text-nowrap" style="min-width:80px;">' . $size . '</div>';

        // Modified
        $html .= '<div class="text-muted small text-end text-nowrap" style="min-width:150px;">' . $modified . '</div>';

        // Actions dropdown
        $html .= '<div class="text-center" style="min-width:100px;">';
        if ($writable) {
            $html .= '<div class="dropdown d-inline-block">';
            $html .= '<button class="btn btn-sm btn-outline-secondary dropdown-toggle py-0 px-1" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>';
            $html .= '<ul class="dropdown-menu dropdown-menu-end small">';

            if ($isDir) {
                $html .= '<li><a class="dropdown-item gc-fm-action-rename" href="#"><i class="bi bi-pencil me-2"></i>' . $lblRename . '</a></li>';
                $html .= '<li><a class="dropdown-item gc-fm-action-delete" href="#"><i class="bi bi-trash me-2 text-danger"></i>' . $lblDelete . '</a></li>';
            } else {
                $html .= '<li><a class="dropdown-item gc-fm-action-download" href="' . $url . '" download><i class="bi bi-download me-2"></i>' . $lblDownload . '</a></li>';
                if ($isImage) {
                    $html .= '<li><a class="dropdown-item gc-fm-action-preview" href="#" data-url="' . $url . '"><i class="bi bi-eye me-2"></i>' . $lblPreview . '</a></li>';
                }
                $html .= '<li><hr class="dropdown-divider"></li>';
                $html .= '<li><a class="dropdown-item gc-fm-action-rename" href="#"><i class="bi bi-pencil me-2"></i>' . $lblRename . '</a></li>';
                $html .= '<li><a class="dropdown-item gc-fm-action-move" href="#"><i class="bi bi-arrows-move me-2"></i>' . $lblMove . '</a></li>';
                $html .= '<li><a class="dropdown-item gc-fm-action-copy" href="#"><i class="bi bi-files me-2"></i>' . $lblCopy . '</a></li>';
                $html .= '<li><hr class="dropdown-divider"></li>';
                $html .= '<li><a class="dropdown-item gc-fm-action-delete" href="#"><i class="bi bi-trash me-2 text-danger"></i>' . $lblDelete . '</a></li>';
            }

            $html .= '</ul></div>';
        } else {
            if (!$isDir) {
                $html .= '<a href="' . $url . '" class="btn btn-sm btn-outline-secondary py-0 px-1" download><i class="bi bi-download"></i></a>';
            }
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    public function renderFolderTree(
        array $tree,
        array $languageStrings = [],
        string $currentPath = '',
        int $depth = 1
    ): string
    {
        if (empty($tree)) {
            return '';
        }

        $html = '<ul class="gc-fm-tree-children list-unstyled" style="padding-left:' . ($depth * 16) . 'px;">';
        foreach ($tree as $node) {
            $name = htmlspecialchars($node['name']);
            $path = htmlspecialchars($node['path']);
            $hasChildren = !empty($node['children']);
            $isActive = $path === $currentPath;

            $html .= '<li class="gc-fm-tree-item" data-path="' . $path . '">';
            $html .= '<a href="#" class="text-decoration-none d-flex align-items-center py-1 px-2 rounded small' . ($isActive ? ' active' : '') . '">';
            if ($hasChildren) {
                $html .= '<i class="bi bi-chevron-right gc-fm-tree-toggle me-1" style="font-size:0.65rem;"></i>';
            } else {
                $html .= '<span class="me-1" style="width:0.65rem;"></span>';
            }
            $html .= '<i class="bi bi-folder text-warning me-1"></i>';
            $html .= '<span class="text-truncate">' . $name . '</span>';
            $html .= '</a>';
            if ($hasChildren) {
                $html .= $this->renderFolderTree($node['children'], [], $currentPath, $depth + 1);
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private function renderBreadcrumb(array $crumbs, array $lang): string
    {
        $html = '';
        $total = count($crumbs);
        $lblRoot = $lang['file_manager_root'] ?? 'Root';

        foreach ($crumbs as $i => $crumb) {
            $name = $i === 0 ? $lblRoot : htmlspecialchars($crumb['name']);
            $path = htmlspecialchars($crumb['path']);

            if ($i < $total - 1) {
                $html .= '<a href="#" class="text-decoration-none text-muted gc-fm-breadcrumb-link" data-path="' . $path . '">' . $name . '</a>';
                $html .= '<span class="text-muted">&rsaquo;</span>';
            } else {
                $html .= '<span class="fw-semibold text-dark">' . $name . '</span>';
            }
        }

        return $html;
    }
}
