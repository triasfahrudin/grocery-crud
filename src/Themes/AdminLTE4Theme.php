<?php

declare(strict_types=1);

namespace GroceryCrud\Themes;

class AdminLTE4Theme implements ThemeInterface
{
    /** @var array<string, string> */
    private array $languageStrings = [];

    /**
     * Set language strings for the theme.
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
        ];
    }

    public function getJsFiles(): array
    {
        return [
            'https://code.jquery.com/jquery-3.7.1.min.js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
            'https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/js/adminlte.min.js',
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

        $columnFilters = $data['columnFilters'] ?? [];
        $currentFilters = $data['currentFilters'] ?? [];
        $batchActions  = $data['batchActions'] ?? [];
        $hasBatch      = !empty($batchActions);

        $totalPages   = $perPage > 0 ? (int) ceil($totalCount / $perPage) : 1;
        $colspan      = count($columns) + ($showActions ? 1 : 0) + (count($customActions) > 0 ? count($customActions) : 0) + ($hasBatch ? 1 : 0);

        // Pre-resolve language strings
        $lblExport       = $lang['export'] ?? 'Export';
        $lblExportCsv    = $lang['export_csv'] ?? 'Export CSV';
        $lblExportExcel  = $lang['export_excel'] ?? 'Export Excel';
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

        // Export buttons
        if ($enableExport && !empty($exportFormats)) {
            $html .= '<div class="dropdown me-2">';
            $html .= '<button class="btn btn-tool dropdown-toggle" type="button" data-bs-toggle="dropdown">';
            $html .= '<i class="bi bi-download"></i>';
            $html .= '</button>';
            $html .= '<ul class="dropdown-menu dropdown-menu-right">';
            foreach ($exportFormats as $format) {
                $label = $format === 'csv' ? $lblExportCsv : $lblExportExcel;
                $html .= '<li><a class="dropdown-item" href="#" data-export="' . $format . '">' . $label . '</a></li>';
            }
            $html .= '</ul></div>';
        }

        // Add button
        if ($hasAdd) {
            $html .= '<button type="button" class="btn btn-primary btn-sm btn-gc-add">';
            $html .= '<i class="bi bi-plus-lg me-1"></i>' . $lblAddRecord;
            $html .= '</button>';
        }

        $html .= '</div></div>';
        $html .= '<div class="card-body">';

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
                $extraClass = $actionId === 'delete_selected' ? ' btn-danger' : ' btn-outline-secondary';
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
            $html .= '<th class="text-nowrap gc-sortable" data-sort-field="' . $col . '" data-sort-dir="' . $nextDir . '">' . htmlspecialchars($label) . $arrow . '</th>';
        }

        if ($showActions || !empty($customActions)) {
            $html .= '<th class="text-center text-nowrap" style="width:120px">' . $lblActions . '</th>';
        }

        $html .= '</tr>';

        // Filter row
        if (!empty($columnFilters)) {
            $html .= '<tr class="gc-filter-row">';
            if ($hasBatch) {
                $html .= '<td></td>';
            }
            foreach ($columns as $col) {
                $html .= '<td>';
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
                    $html .= '<td>' . $value . '</td>';
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
                        $html .= '<a href="' . $url . '" class="btn btn-outline-secondary" title="' . htmlspecialchars($actionLabel) . '">';
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

            $html .= '<div class="mb-3' . ($fieldError ? ' has-error' : '') . '">';
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
                    $html .= '<input type="hidden" name="' . $fieldName . '_existing" value="' . htmlspecialchars(basename((string) $value)) . '">';
                }
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
                    $template .= $this->renderRepeaterSubField($inputName, $inputId, $sfType, '', $sfOpts);
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
    private function renderRepeaterSubField(string $name, string $id, string $type, mixed $value, array $options = []): string
    {
        switch ($type) {
            case 'textarea':
                return '<textarea class="form-control form-control-sm" id="' . $id . '" name="' . $name . '" rows="2">' . htmlspecialchars((string) $value) . '</textarea>';

            case 'integer':
            case 'numeric':
                return '<input type="number" step="any" class="form-control form-control-sm" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '">';

            case 'select':
            case 'dropdown':
                $html = '<select class="form-select form-select-sm" id="' . $id . '" name="' . $name . '">';
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
                return '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="' . $id . '" name="' . $name . '" value="1"' . $checked . '></div>';

            case 'hidden':
                return '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '">';

            default: // text, string
                return '<input type="text" class="form-control form-control-sm" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '">';
        }
    }
}
