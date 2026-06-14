<?php

declare(strict_types=1);

namespace GroceryCrud\Themes;

class MaterializeTheme implements ThemeInterface
{
    private array $languageStrings = [];

    public function setLanguageStrings(array $strings): void
    {
        $this->languageStrings = $strings;
    }

    public function getName(): string
    {
        return 'materialize';
    }

    public function getCssFiles(): array
    {
        return [
            'https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css',
            'https://fonts.googleapis.com/icon?family=Material+Icons',
            'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css',
        ];
    }

    public function getJsFiles(): array
    {
        return [
            'https://code.jquery.com/jquery-3.7.1.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js',
            'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.min.js',
        ];
    }

    public function renderList(array $data): string
    {
        $lang = $this->languageStrings;

        $columns       = $data['columns'] ?? [];
        $columnLabels  = $data['columnLabels'] ?? [];
        $records       = $data['records'] ?? [];
        $pager         = $data['pager'] ?? null;
        $totalCount    = (int) ($data['totalCount'] ?? 0);
        $perPage       = (int) ($data['perPage'] ?? 25);
        $currentPage   = (int) ($data['currentPage'] ?? 1);
        $subject       = $data['subject'] ?? 'Records';
        $actions       = $data['actions'] ?? ['add', 'edit', 'delete'];
        $hasEdit       = in_array('edit', $actions, true);
        $hasDelete     = in_array('delete', $actions, true);
        $hasAdd        = in_array('add', $actions, true);
        $primaryKey    = $data['primaryKey'] ?? 'id';
        $showActions   = $hasEdit || $hasDelete;
        $customActions = $data['customActions'] ?? [];
        $crudId        = $data['crudId'] ?? 'crud_' . uniqid();
        $searchable    = (bool) ($data['searchable'] ?? false);
        $sortField     = $data['sortField'] ?? null;
        $sortDir       = $data['sortDir'] ?? 'ASC';
        $exportFormats    = $data['exportFormats'] ?? [];
        $enableExport     = (bool) ($data['enableExport'] ?? false);
        $columnFilters    = $data['columnFilters'] ?? [];
        $currentFilters   = $data['currentFilters'] ?? [];
        $batchActions     = $data['batchActions'] ?? [];
        $hasBatch         = !empty($batchActions);
        $enableFilters    = (bool) ($data['enableFilters'] ?? true);
        $enableColumns    = (bool) ($data['enableColumns'] ?? true);
        $enableSettings   = (bool) ($data['enableSettings'] ?? true);
        $softDelete       = (bool) ($data['softDelete'] ?? false);
        $trashedView      = (bool) ($data['trashedView'] ?? false);
        $subGrids         = $data['subGrids'] ?? [];
        $hasSubGrid       = !empty($subGrids) && !$trashedView;
        $fieldOptions     = $data['fieldOptions'] ?? [];

        if ($trashedView) {
            $hasDelete = false;
            $hasEdit   = false;
        }

        $totalPages = $perPage > 0 ? (int) ceil($totalCount / $perPage) : 1;
        $colspan    = count($columns) + ($showActions ? 1 : 0) + (count($customActions) > 0 ? count($customActions) : 0) + ($hasBatch ? 1 : 0) + ($hasSubGrid ? 1 : 0);

        $lblExport      = $lang['export'] ?? 'Export';
        $lblExportCsv   = $lang['export_csv'] ?? 'Export CSV';
        $lblExportExcel = $lang['export_excel'] ?? 'Export Excel';
        $lblExportPdf   = $lang['export_pdf'] ?? 'Export PDF';
        $lblPrintView   = $lang['print_view'] ?? 'Print View';
        $lblAddRecord   = $lang['add_record'] ?? 'Add Record';
        $lblSearch      = $lang['search'] ?? 'Search';
        $lblActions     = $lang['actions'] ?? 'Actions';
        $lblNoRecords   = $lang['no_records'] ?? 'No records found.';
        $lblEdit        = $lang['edit'] ?? 'Edit';
        $lblDelete      = $lang['delete'] ?? 'Delete';
        $lblSelectAll   = $lang['select_all'] ?? 'Select All';
        $lblFilter      = $lang['filter'] ?? 'Filter';
        $lblAll         = $lang['all'] ?? 'All';
        $lblOf          = $lang['of'] ?? 'of';
        $lblRecords     = $lang['records'] ?? 'records';
        $lblPrevious    = $lang['previous'] ?? 'Previous';
        $lblNext        = $lang['next'] ?? 'Next';

        $html = '<div class="grocery-crud-wrapper" id="' . $crudId . '">';
        $html .= '<div class="card">';
        $html .= '<div class="card-content">';
        $html .= '<div class="card-title" style="display:flex;justify-content:space-between;align-items:center">';
        $html .= '<span><i class="material-icons left">table_chart</i>' . $subject . '</span>';
        $html .= '<div class="right" style="display:flex;gap:4px;align-items:center">';

        if ($enableExport && !empty($exportFormats)) {
            $html .= '<a class="dropdown-trigger btn-small waves-effect waves-light grey lighten-3 black-text" href="#" data-target="' . $crudId . '_export"><i class="material-icons left">file_download</i>' . $lblExport . '</a>';
            $html .= '<ul id="' . $crudId . '_export" class="dropdown-content">';
            foreach ($exportFormats as $format) {
                $label = match ($format) {
                    'csv'       => $lblExportCsv,
                    'excel'     => $lblExportExcel,
                    'pdf'       => $lblExportPdf,
                    'print'     => $lblPrintView,
                    default     => ucfirst($format),
                };
                $html .= '<li><a href="#" data-export="' . $format . '">' . $label . '</a></li>';
            }
            $html .= '</ul>';
        }

        if ($enableFilters) {
            $html .= '<a class="btn-small waves-effect waves-light grey lighten-3 black-text gc-tool-btn gc-filter-btn" title="' . ($lang['filters'] ?? 'Filters') . '"><i class="material-icons">filter_list</i></a>';
        }

        if ($enableColumns) {
            $html .= '<a class="dropdown-trigger btn-small waves-effect waves-light grey lighten-3 black-text gc-tool-btn gc-btn-columns" href="#" data-target="' . $crudId . '_cols" title="' . ($lang['columns'] ?? 'Columns') . '"><i class="material-icons">view_column</i></a>';
            $html .= '<div id="' . $crudId . '_cols" class="dropdown-content gc-columns-menu" style="min-width:200px"></div>';
        }

        if ($enableSettings) {
            $html .= '<a class="dropdown-trigger btn-small waves-effect waves-light grey lighten-3 black-text gc-tool-btn gc-btn-settings" href="#" data-target="' . $crudId . '_settings" title="' . ($lang['settings'] ?? 'Settings') . '"><i class="material-icons">settings</i></a>';
            $html .= '<ul id="' . $crudId . '_settings" class="dropdown-content gc-settings-menu">';
            $html .= '<li><a class="gc-settings-save" href="#"><i class="material-icons left">save</i>' . ($lang['save_settings'] ?? 'Save') . '</a></li>';
            $html .= '<li><a class="gc-settings-load" href="#"><i class="material-icons left">restore</i>' . ($lang['load_settings'] ?? 'Load') . '</a></li>';
            $html .= '<li class="divider"></li>';
            $html .= '<li><a class="gc-settings-reset" href="#"><i class="material-icons left">delete</i>' . ($lang['reset_settings'] ?? 'Reset') . '</a></li>';
            $html .= '</ul>';
        }

        if ($softDelete) {
            if ($trashedView) {
                $html .= '<a class="btn-small waves-effect waves-light grey lighten-3 black-text gc-tool-btn gc-btn-active" title="' . ($lang['active_list'] ?? 'Active Records') . '"><i class="material-icons left">list</i>' . ($lang['active_list'] ?? 'Active') . '</a>';
            } else {
                $html .= '<a class="btn-small waves-effect waves-light grey lighten-3 black-text gc-tool-btn gc-btn-trash" title="' . ($lang['trash_list'] ?? 'Trash') . '"><i class="material-icons left">delete</i>' . ($lang['trash_list'] ?? 'Trash') . '</a>';
            }
        }

        if ($hasAdd) {
            $html .= '<a class="btn-small waves-effect waves-light blue btn-gc-add"><i class="material-icons left">add</i>' . $lblAddRecord . '</a>';
        }

        $html .= '</div></div>';

        // Filter panel
        if ($enableFilters) {
            $html .= '<div class="gc-filter-panel card-panel grey lighten-4" style="display:none">';
            $html .= '<div class="gc-filter-rows">';
            $html .= '<div class="gc-filter-item gc-filter-item-template row" style="display:none">';
            $html .= '<div class="col s3"><select class="browser-default gc-filter-col"><option value="">' . ($lang['select_column'] ?? 'Column') . '</option></select></div>';
            $html .= '<div class="col s3"><select class="browser-default gc-filter-op">';
            $html .= '<option value="contains">' . ($lang['contains'] ?? 'Contains') . '</option>';
            $html .= '<option value="equals">' . ($lang['equals'] ?? 'Equals') . '</option>';
            $html .= '<option value="not_equal">' . ($lang['not_equal'] ?? 'Not equal') . '</option>';
            $html .= '<option value="starts_with">' . ($lang['starts_with'] ?? 'Starts with') . '</option>';
            $html .= '<option value="ends_with">' . ($lang['ends_with'] ?? 'Ends with') . '</option>';
            $html .= '<option value="greater_than">' . ($lang['greater_than'] ?? 'Greater than') . '</option>';
            $html .= '<option value="less_than">' . ($lang['less_than'] ?? 'Less than') . '</option>';
            $html .= '</select></div>';
            $html .= '<div class="col s3"><input type="text" class="gc-filter-val" placeholder="' . ($lang['value'] ?? 'Value') . '"></div>';
            $html .= '<div class="col s1"><a class="btn-flat red-text gc-filter-item-remove" title="' . ($lang['remove'] ?? 'Remove') . '"><i class="material-icons">close</i></a></div>';
            $html .= '</div></div>';
            $html .= '<div class="gc-filter-actions">';
            $html .= '<a class="waves-effect waves-teal btn-flat gc-filter-add">+ ' . ($lang['add_filter'] ?? 'Add Filter') . '</a> ';
            $html .= '<a class="waves-effect waves-light btn blue gc-filter-apply">' . ($lang['apply'] ?? 'Apply') . '</a> ';
            $html .= '<a class="waves-effect waves-light btn-flat gc-filter-clear">' . ($lang['clear'] ?? 'Clear') . '</a>';
            $html .= '</div></div>';
        }

        // Search + batch
        $html .= '<div class="row valign-wrapper" style="margin-bottom:10px">';
        $searchCol = $searchable ? 'col s6' : 'col s6';
        if ($searchable) {
            $html .= '<div class="' . $searchCol . '">';
            $html .= '<div class="input-field" style="margin-top:0">';
            $html .= '<input type="text" class="gc-search-input" id="' . $crudId . '_search" placeholder="' . $lblSearch . '...">';
            $html .= '<label for="' . $crudId . '_search" class="active"><i class="material-icons">search</i></label>';
            $html .= '<button class="btn-flat gc-search-clear" type="button" style="display:none;position:absolute;right:40px;top:0"><i class="material-icons">close</i></button>';
            $html .= '</div></div>';
        } else {
            $html .= '<div class="col s6"></div>';
        }

        if ($hasBatch) {
            $html .= '<div class="col s6 right-align"><div class="gc-batch-toolbar" style="display:none">';
            $html .= '<span class="chip gc-selected-count"><span class="gc-selected-num">0</span> ' . $lblRecords . '</span>';
            foreach ($batchActions as $actionId => $label) {
                $color = match ($actionId) {
                    'delete_selected'  => 'red',
                    'restore_selected' => 'green',
                    default            => 'grey lighten-3 black-text',
                };
                $html .= '<a class="waves-effect waves-light btn-small ' . $color . ' gc-batch-action" data-batch-action="' . $actionId . '">' . htmlspecialchars($label) . '</a> ';
            }
            $html .= '</div></div>';
        }
        $html .= '</div>';

        // Table
        $html .= '<table class="responsive-table striped gc-table" data-crud-id="' . $crudId . '">';
        $html .= '<thead>';
        $html .= '<tr>';
        if ($hasSubGrid) {
            $html .= '<th style="width:40px"><i class="material-icons">chevron_right</i></th>';
        }
        if ($hasBatch) {
            $html .= '<th style="width:40px"><label><input type="checkbox" class="gc-select-all" title="' . $lblSelectAll . '"><span></span></label></th>';
        }
        foreach ($columns as $col) {
            $label = $columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
            $isSorted = $col === $sortField;
            $dir = $isSorted ? $sortDir : 'ASC';
            $nextDir = $isSorted && $dir === 'ASC' ? 'DESC' : 'ASC';
            $arrow = $isSorted ? ($dir === 'ASC' ? ' &#9650;' : ' &#9660;') : '';
            $html .= '<th class="gc-sortable" data-column="' . $col . '" data-label="' . htmlspecialchars($label) . '" data-sort-field="' . $col . '" data-sort-dir="' . $nextDir . '">' . htmlspecialchars($label) . $arrow . '</th>';
        }
        if ($showActions || !empty($customActions)) {
            $html .= '<th style="width:120px">' . $lblActions . '</th>';
        }
        $html .= '</tr>';

        // Column filter row
        if (!empty($columnFilters)) {
            $html .= '<tr>';
            if ($hasSubGrid) {
                $html .= '<td></td>';
            }
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
                        $html .= '<select class="browser-default gc-column-filter" data-filter-field="' . $col . '">';
                        $html .= '<option value="">' . $lblAll . '</option>';
                        foreach ($filterOptions as $optValue => $optLabel) {
                            $selected = (string) $currentVal === (string) $optValue ? ' selected' : '';
                            $html .= '<option value="' . htmlspecialchars((string) $optValue) . '"' . $selected . '>' . htmlspecialchars((string) $optLabel) . '</option>';
                        }
                        $html .= '</select>';
                    } else {
                        $html .= '<input type="text" class="gc-column-filter" data-filter-field="' . $col . '" placeholder="' . $lblFilter . '" value="' . htmlspecialchars((string) $currentVal) . '">';
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
            $html .= '<tr><td colspan="' . $colspan . '" class="center grey-text">' . $lblNoRecords . '</td></tr>';
        } else {
            foreach ($records as $row) {
                $rowId = htmlspecialchars((string) ($row[$primaryKey] ?? ''));
                $trashedClass = $trashedView ? ' class="gc-trashed"' : '';
                $html .= '<tr' . $trashedClass . ' data-parent-id="' . $rowId . '">';
                if ($hasSubGrid) {
                    $sgField = array_key_first($subGrids);
                    $html .= '<td>';
                    $html .= '<a class="btn-flat gc-subgrid-toggle" data-subgrid="' . htmlspecialchars($sgField) . '" data-parent-id="' . $rowId . '" title="Expand"><i class="material-icons">chevron_right</i></a></td>';
                }
                if ($hasBatch) {
                    $html .= '<td><label><input type="checkbox" class="gc-row-checkbox" value="' . $rowId . '"><span></span></label></td>';
                }
                foreach ($columns as $col) {
                    $value = $row[$col] ?? '';
                    $displayValue = $value;
                    if (!empty($fieldOptions[$col]) && isset($fieldOptions[$col][$value])) {
                        $displayValue = $fieldOptions[$col][$value];
                    }
                    $html .= '<td data-column="' . $col . '">' . $displayValue . '</td>';
                }
                if ($showActions || !empty($customActions)) {
                    $html .= '<td class="right" style="white-space:nowrap">';
                    if ($trashedView) {
                        $html .= '<a class="btn-flat green-text btn-gc-restore" data-id="' . $rowId . '" title="' . ($lang['restore'] ?? 'Restore') . '"><i class="material-icons">restore</i></a>';
                    }
                    if ($hasEdit) {
                        $html .= '<a class="btn-flat blue-text btn-gc-edit" data-id="' . $rowId . '" title="' . $lblEdit . '"><i class="material-icons">edit</i></a>';
                    }
                    foreach ($customActions as $action) {
                        $rowId = (string) ($row[$primaryKey] ?? '');
                        $url = str_replace('{id}', $rowId, $action['url'] ?? '#');
                        $actionLabel = $action['label'] ?? '';
                        $actionIcon = $action['icon'] ?? '';
                        $html .= '<a href="' . $url . '" class="btn-flat" title="' . htmlspecialchars($actionLabel) . '">';
                        if ($actionIcon !== '') {
                            $html .= '<i class="' . htmlspecialchars($actionIcon) . '"></i>';
                        } else {
                            $html .= htmlspecialchars($actionLabel);
                        }
                        $html .= '</a>';
                    }
                    if ($hasDelete) {
                        $html .= '<a class="btn-flat red-text btn-gc-delete" data-id="' . $rowId . '" title="' . $lblDelete . '"><i class="material-icons">delete</i></a>';
                    }
                    $html .= '</td>';
                }
                $html .= '</tr>';

                // Sub-grid row
                if ($hasSubGrid) {
                    $sgField = array_key_first($subGrids);
                    $sgColspan = $colspan;
                    $html .= '<tr class="gc-subgrid-row" style="display:none" data-parent-id="' . $rowId . '">';
                    $html .= '<td colspan="' . $sgColspan . '">';
                    $html .= '<div class="gc-subgrid-content" data-subgrid="' . htmlspecialchars($sgField) . '">';
                    $html .= '<div class="gc-loading-sub grey-text"><i class="material-icons">sync</i> Memuat data...</div>';
                    $html .= '</div></td></tr>';
                }
            }
        }
        $html .= '</tbody></table>';

        // Pagination
        if ($totalPages > 1 && $pager !== null) {
            $from = $pager['from'] ?? 0;
            $to   = $pager['to'] ?? 0;
            $html .= '<div class="row" style="margin-top:15px">';
            $html .= '<div class="col s6"><span class="grey-text">' . $from . '&ndash;' . $to . ' ' . $lblOf . ' ' . $totalCount . ' ' . $lblRecords . '</span></div>';
            $html .= '<div class="col s6 right-align"><ul class="pagination">';
            $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
            $html .= '<li class="' . ($prevDisabled ?: 'waves-effect') . '"><a class="gc-page-link" href="#" data-page="' . ($currentPage - 1) . '"><i class="material-icons">chevron_left</i></a></li>';
            $startPage = max(1, $currentPage - 2);
            $endPage   = min($totalPages, $currentPage + 2);

            if ($startPage > 1) {
                $html .= '<li class="waves-effect"><a class="gc-page-link" href="#" data-page="1">1</a></li>';
                if ($startPage > 2) {
                    $html .= '<li class="disabled"><a href="#"><i class="material-icons">more_horiz</i></a></li>';
                }
            }
            for ($i = $startPage; $i <= $endPage; $i++) {
                $active = $i === $currentPage ? ' active blue' : ' waves-effect';
                $html .= '<li class="' . $active . '"><a class="gc-page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
            }
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    $html .= '<li class="disabled"><a href="#"><i class="material-icons">more_horiz</i></a></li>';
                }
                $html .= '<li class="waves-effect"><a class="gc-page-link" href="#" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
            }
            $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
            $html .= '<li class="' . ($nextDisabled ?: 'waves-effect') . '"><a class="gc-page-link" href="#" data-page="' . ($currentPage + 1) . '"><i class="material-icons">chevron_right</i></a></li>';
            $html .= '</ul></div></div>';
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

    public function renderImportForm(array $data): string
    {
        // Delegasikan ke form impor Bootstrap5Theme untuk UI yang konsisten
        $bootstrap5 = new Bootstrap5Theme();
        return $bootstrap5->renderImportForm($data);
    }

    private function renderForm(string $mode, array $data): string
    {
        $lang = $this->languageStrings;
        $fields       = $data['fields'] ?? [];
        $fieldLabels  = $data['fieldLabels'] ?? [];
        $fieldTypes   = $data['fieldTypes'] ?? [];
        $fieldValues  = $data['fieldValues'] ?? [];
        $fieldOptions = $data['fieldOptions'] ?? [];
        $primaryKey   = $data['primaryKey'] ?? 'id';
        $recordId     = $data['recordId'] ?? null;
        $errors       = $data['errors'] ?? [];
        $subject      = $data['subject'] ?? 'Records';
        $crudId       = $data['crudId'] ?? 'crud_' . uniqid();

        $isEdit   = $mode === 'edit';
        $lblTitle = $isEdit ? ($lang['edit_record'] ?? 'Edit Record') : ($lang['add_record'] ?? 'Add Record');
        $lblSave   = $lang['save'] ?? 'Save';
        $lblCancel = $lang['cancel'] ?? 'Cancel';
        $modalIcon = $isEdit ? 'edit' : 'add';

        $html = '<div class="grocery-crud-form-wrapper" id="' . $crudId . '_form">';
        $html .= '<div class="card">';
        $html .= '<div class="card-content">';
        $html .= '<span class="card-title"><i class="material-icons left">' . $modalIcon . '</i>' . $lblTitle . '</span>';
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

            $html .= '<div class="input-field' . ($fieldError ? ' has-error' : '') . '">';
            $html .= $this->renderFormField($field, $type, $value, $options, $isReadonly, $isUpload, $data);
            $html .= '<label for="gc_field_' . $field . '">';
            $html .= htmlspecialchars($label);
            if ($isRequired) {
                $html .= ' <span class="red-text">*</span>';
            }
            $html .= '</label>';
            if ($fieldError) {
                $html .= '<span class="helper-text red-text">' . htmlspecialchars($fieldError) . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '<div class="card-action" style="padding-left:0">';
        $html .= '<button type="submit" class="waves-effect waves-light btn blue"><i class="material-icons left">check</i>' . $lblSave . '</button> ';
        $html .= '<a class="waves-effect waves-light btn-flat gc-form-close"><i class="material-icons left">close</i>' . $lblCancel . '</a>';
        $html .= '</div>';
        $html .= '</form></div></div></div>';
        return $html;
    }

    private function renderFormField(string $field, string $type, mixed $value, array $options, bool $isReadonly, bool $isUpload, array $data): string
    {
        $readonlyAttr = $isReadonly ? ' readonly' : '';
        $fieldId      = 'gc_field_' . $field;
        $fieldName    = $field;

        $repeaterDefs = $data['repeaterFields'] ?? [];
        $repeaterData = $data['repeaterData'] ?? [];
        $rDef         = $repeaterDefs[$field] ?? null;
        $rValues      = $repeaterData[$field] ?? [];

        $html = '';
        switch ($type) {
            case 'textarea':
            case 'text areas':
                $html .= '<textarea class="materialize-textarea" id="' . $fieldId . '" name="' . $fieldName . '" rows="4"' . $readonlyAttr . '>' . htmlspecialchars((string) $value) . '</textarea>';
                break;
            case 'hidden':
                $html .= '<input type="hidden" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '">';
                break;
            case 'integer':
            case 'numeric':
                $html .= '<input type="number" step="any" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'date':
                $html .= '<input type="date" class="datepicker" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'datetime':
                $html .= '<input type="datetime-local" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'time':
                $html .= '<input type="time" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'email':
                $html .= '<input type="email" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'url':
                $html .= '<input type="url" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'phone':
                $html .= '<input type="tel" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'color':
                $html .= '<input type="color" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'password':
                $html .= '<input type="password" id="' . $fieldId . '" name="' . $fieldName . '"' . $readonlyAttr . '>';
                if (!empty($value)) {
                    $html .= '<span class="helper-text">Leave empty to keep current password.</span>';
                }
                break;
            case 'true_false':
            case 'boolean':
                $checked = !empty($value) ? ' checked' : '';
                $html .= '<div class="switch"><label>';
                $html .= '<input type="checkbox" id="' . $fieldId . '" name="' . $fieldName . '" value="1"' . $checked . $readonlyAttr . '>';
                $html .= '<span class="lever"></span>';
                $html .= '</label></div>';
                break;
            case 'dropdown':
            case 'enum':
            case 'relation':
                $html .= '<select class="browser-default" id="' . $fieldId . '" name="' . $fieldName . '"' . ($isReadonly ? ' disabled' : '') . '>';
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
                $html .= '<div style="max-height:200px;overflow-y:auto;border:1px solid #ddd;padding:10px;border-radius:4px">';
                if (!empty($options) && is_array($options)) {
                    $selectedValues = is_array($value) ? $value : explode(',', (string) $value);
                    foreach ($options as $optValue => $optLabel) {
                        $checked = in_array((string) $optValue, $selectedValues, true) ? ' checked' : '';
                        $optId = $fieldId . '_' . md5((string) $optValue);
                        $html .= '<label for="' . $optId . '">';
                        $html .= '<input type="checkbox" id="' . $optId . '" name="' . $fieldName . '[]" value="' . htmlspecialchars((string) $optValue) . '"' . $checked . ($isReadonly ? ' disabled' : '') . '>';
                        $html .= '<span>' . htmlspecialchars((string) $optLabel) . '</span>';
                        $html .= '</label>';
                    }
                }
                $html .= '</div>';
                break;
            case 'image':
            case 'file':
                $html .= '<div class="file-field input-field">';
                $html .= '<div class="btn blue lighten-1"><span><i class="material-icons">file_upload</i></span>';
                $html .= '<input type="file" id="' . $fieldId . '" name="' . $fieldName . '"' . $readonlyAttr . '>';
                $html .= '</div>';
                $html .= '<div class="file-path-wrapper"><input class="file-path validate" type="text"></div>';
                $html .= '</div>';
                if (!empty($value)) {
                    $html .= '<div class="mt-2">';
                    if ($type === 'image') {
                        $html .= '<img src="' . htmlspecialchars((string) $value) . '" class="responsive-img" style="max-height:100px" alt="">';
                    } else {
                        $html .= '<a href="' . htmlspecialchars((string) $value) . '" target="_blank" class="waves-effect waves-light btn-small grey lighten-3 black-text">';
                        $html .= '<i class="material-icons left">attach_file</i> ' . basename((string) $value) . '</a>';
                    }
                    $html .= '</div>';
                    $html .= '<input type="hidden" name="' . $fieldName . '_existing" value="' . htmlspecialchars((string) $value) . '">';
                }
                break;
            case 'richtext':
                $html .= '<div class="gc-richtext-editor" id="' . $fieldId . '_editor">' . $value . '</div>';
                $html .= '<textarea style="display:none" id="' . $fieldId . '" name="' . $fieldName . '">' . htmlspecialchars((string) $value) . '</textarea>';
                break;
            case 'read_only':
                $html .= '<input type="text" id="' . $fieldId . '" value="' . htmlspecialchars((string) $value) . '" readonly disabled>';
                break;
            case 'repeater':
                if ($rDef === null) break;
                $repeatables = $rDef['repeatables'] ?? [];
                $html .= '<div class="gc-repeater-container card-panel">';
                foreach ($rValues as $rIndex => $rItem) {
                    $html .= '<div class="gc-repeater-item card"><div class="card-content">';
                    $html .= '<div class="right"><a class="btn-flat red-text gc-repeater-remove"><i class="material-icons">delete</i></a></div>';
                    foreach ($repeatables as $subField) {
                        $sfName  = $subField['name'];
                        $sfLabel = $subField['label'] ?? ucfirst($sfName);
                        $sfType  = $subField['type'] ?? 'text';
                        $sfOpts  = $subField['options'] ?? [];
                        $sfValue = $rItem[$sfName] ?? '';
                        $inputName = $fieldName . '[' . $rIndex . '][' . $sfName . ']';
                        $inputId   = $fieldId . '_' . $rIndex . '_' . $sfName;
                        $html .= '<div class="input-field">';
                        $html .= $this->renderRepeaterSubField($inputName, $inputId, $sfType, $sfValue, $sfOpts);
                        $html .= '<label for="' . $inputId . '">' . htmlspecialchars($sfLabel) . '</label>';
                        $html .= '</div>';
                    }
                    $html .= '</div></div>';
                }
                $template = '<div class="gc-repeater-item card"><div class="card-content">';
                $template .= '<div class="right"><a class="btn-flat red-text gc-repeater-remove"><i class="material-icons">delete</i></a></div>';
                foreach ($repeatables as $subField) {
                    $sfName  = $subField['name'];
                    $sfLabel = $subField['label'] ?? ucfirst($sfName);
                    $sfType  = $subField['type'] ?? 'text';
                    $sfOpts  = $subField['options'] ?? [];
                    $inputName = $fieldName . '[__INDEX__][' . $sfName . ']';
                    $inputId   = $fieldId . '__INDEX__' . $sfName;
                    $template .= '<div class="input-field">';
                    $template .= $this->renderRepeaterSubField($inputName, $inputId, $sfType, '', $sfOpts, true);
                    $template .= '<label for="' . $inputId . '">' . htmlspecialchars($sfLabel) . '</label>';
                    $template .= '</div>';
                }
                $template .= '</div>';
                $html .= '<div class="gc-repeater-template" style="display:none">' . $template . '</div>';
                $html .= '<button type="button" class="waves-effect waves-light btn-small blue lighten-2 mt-2 gc-repeater-add"><i class="material-icons left">add</i> Add Item</button>';
                $html .= '</div>';
                break;
            default:
                $html .= '<input type="text" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
        }
        return $html;
    }

    private function renderRepeaterSubField(string $name, string $id, string $type, mixed $value, array $options = [], bool $disabled = false): string
    {
        $d = $disabled ? ' disabled' : '';
        switch ($type) {
            case 'textarea':
                return '<textarea class="materialize-textarea" id="' . $id . '" name="' . $name . '" rows="2"' . $d . '>' . htmlspecialchars((string) $value) . '</textarea>';
            case 'integer':
            case 'numeric':
                return '<input type="number" step="any" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';
            case 'select':
            case 'dropdown':
                $html = '<select class="browser-default" id="' . $id . '" name="' . $name . '"' . $d . '>';
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
                return '<div class="switch"><label><input type="checkbox" id="' . $id . '" name="' . $name . '" value="1"' . $checked . $d . '><span class="lever"></span></label></div>';
            case 'hidden':
                return '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';
            default:
                return '<input type="text" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';
        }
    }

    public function renderSubGrid(array $config, array $records): string
    {
        $columns      = $config['columns'] ?? [];
        $columnLabels = $config['columnLabels'] ?? [];
        $relatedTable = $config['relatedTable'] ?? '';
        $recordCount  = count($records);

        $html = '<div class="gc-subgrid-inner">';
        $tableLabel = ucfirst(str_replace('_', ' ', $relatedTable));
        $html .= '<div class="card" style="margin:0">';
        $html .= '<div class="card-content" style="padding:10px">';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">';
        $html .= '<span class="card-title" style="font-size:1rem;margin:0"><i class="material-icons left">grid_on</i>' . htmlspecialchars($tableLabel) . '</span>';
        $html .= '<span class="chip blue lighten-4 blue-text">' . $recordCount . ' data</span>';
        $html .= '</div>';
        $html .= '<table class="responsive-table striped gc-subgrid-table">';
        $html .= '<thead><tr>';
        foreach ($columns as $col) {
            $label = $columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
            $html .= '<th>' . htmlspecialchars($label) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if (empty($records)) {
            $colspan = count($columns);
            $html .= '<tr><td colspan="' . $colspan . '" class="center grey-text">Tidak ada data terkait.</td></tr>';
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
        $html .= '</tbody></table></div></div></div>';
        return $html;
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
        $lblBack          = $lang['back'] ?? 'Back';

        $html = '<div class="gc-file-manager" id="' . $crudId . '_fm">';
        $html .= '<div class="card">';
        $html .= '<div class="card-content" style="padding:0;">';
        $html .= '<div class="card-title" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;margin:0;">';
        $html .= '<span style="font-weight:600;font-size:1.2rem;"><i class="bi bi-folder2-open" style="margin-right:8px;"></i>' . $lblFileManager . '</span>';
        $html .= '<a class="btn-small waves-effect waves-light grey lighten-3 black-text gc-fm-back-to-list" href="#"><i class="bi bi-arrow-left" style="margin-right:4px;"></i>' . $lblBack . '</a>';
        $html .= '</div>';
        $html .= '<div class="gc-fm-layout" style="display:flex;">';

        // Sidebar: Folder Tree
        $html .= '<div class="gc-fm-sidebar" style="min-width:220px;max-width:260px;overflow-y:auto;border-right:1px solid #e0e0e0;padding:12px 16px;">';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">';
        $html .= '<span style="font-weight:600;font-size:0.8rem;text-transform:uppercase;color:#9e9e9e;"><i class="bi bi-folder" style="margin-right:4px;"></i>' . ($lang['file_manager_folders'] ?? 'Folders') . '</span>';
        $html .= '<a class="btn-small waves-effect waves-light grey lighten-3 black-text gc-fm-refresh-tree" href="#" title="' . $lblRefresh . '" style="padding:0 8px;"><i class="bi bi-arrow-clockwise"></i></a>';
        $html .= '</div>';
        $html .= '<ul class="gc-fm-tree" style="list-style:none;margin:0;padding:0;">';
        $html .= '<li class="gc-fm-tree-item gc-fm-tree-root" data-path="">';
        $html .= '<a href="#" style="display:flex;align-items:center;padding:4px 8px;border-radius:4px;text-decoration:none;color:' . ($currentPath === '' ? '#1565c0' : '#333') . ';background:' . ($currentPath === '' ? '#e3f2fd' : 'transparent') . ';">';
        $html .= '<i class="bi bi-folder2-open" style="color:#f9a825;margin-right:8px;"></i><span>' . htmlspecialchars($subject) . '</span>';
        $html .= '</a>';
        $html .= $this->renderFolderTree($tree, [], $currentPath, 1);
        $html .= '</li>';
        $html .= '</ul>';
        $html .= '</div>';

        // Main Content
        $html .= '<div class="gc-fm-content" style="flex:1;min-width:0;display:flex;flex-direction:column;">';

        // Breadcrumb
        $html .= '<div class="gc-fm-breadcrumb" style="border-bottom:1px solid #e0e0e0;padding:8px 12px;background:#f5f5f5;display:flex;align-items:center;gap:4px;font-size:0.9rem;">';
        $html .= $this->renderBreadcrumb($breadcrumb, $lang);
        $html .= '</div>';

        // Toolbar
        $html .= '<div class="gc-fm-toolbar" style="border-bottom:1px solid #e0e0e0;padding:8px 12px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">';
        if ($writable) {
            $html .= '<a class="btn-small waves-effect waves-light blue gc-fm-new-folder" href="#"><i class="bi bi-folder-plus" style="margin-right:4px;"></i>' . $lblNewFolder . '</a>';
            $html .= '<a class="btn-small waves-effect waves-light green gc-fm-upload-btn" href="#"><i class="bi bi-cloud-arrow-up" style="margin-right:4px;"></i>' . $lblUpload . '</a>';
            $html .= '<form class="gc-fm-upload-form" method="post" enctype="multipart/form-data" style="display:none;">';
            $accept = $data['allowedTypes'] ?? '*';
            if ($accept !== '*') {
                $exts = array_map(fn($ext) => '.' . trim($ext), explode('|', $accept));
                $accept = implode(',', $exts);
            }
            $html .= '<input type="file" class="gc-fm-upload-input" multiple accept="' . htmlspecialchars($accept) . '">';
            $html .= '</form>';
        }
        $html .= '<div style="margin-left:auto;display:flex;gap:6px;">';
        $html .= '<div style="display:flex;max-width:220px;position:relative;">';
        $html .= '<input type="text" class="gc-fm-search" placeholder="' . $lblSearch . '" style="border:1px solid #bdbdbd;border-right:none;border-radius:4px 0 0 4px;padding:4px 8px;font-size:0.85rem;">';
        $html .= '<button class="gc-fm-search-btn" type="button" style="border:1px solid #bdbdbd;border-radius:0 4px 4px 0;background:#f5f5f5;padding:4px 8px;cursor:pointer;"><i class="bi bi-search"></i></button>';
        $html .= '<button class="gc-fm-search-clear" type="button" style="border:1px solid #bdbdbd;border-radius:4px;background:#f5f5f5;padding:4px 8px;cursor:pointer;display:none;"><i class="bi bi-x-lg"></i></button>';
        $html .= '</div>';
        $html .= '<a class="btn-small waves-effect waves-light grey lighten-3 black-text gc-fm-refresh" href="#" title="' . $lblRefresh . '"><i class="bi bi-arrow-clockwise"></i></a>';
        $html .= '</div>';
        $html .= '</div>';

        // File list
        $html .= '<div class="gc-fm-list-container" style="padding:12px;flex:1;overflow-y:auto;min-height:350px;">';
        $html .= $this->renderFileManagerList($data);
        $html .= '</div>';

        // Status bar
        $html .= '<div class="gc-fm-status" style="border-top:1px solid #e0e0e0;padding:4px 12px;color:#9e9e9e;font-size:0.85rem;display:flex;justify-content:space-between;">';
        $html .= '<span class="gc-fm-status-path" data-path="' . htmlspecialchars($currentPath) . '">' . ($lang['file_manager_current_dir'] ?? 'Current directory') . ': <strong>' . ($currentPath ?: '/') . '</strong></span>';
        $html .= '<span class="gc-fm-status-count"></span>';
        $html .= '</div>';

        $html .= '</div>'; // .gc-fm-content
        $html .= '</div>'; // .gc-fm-layout
        $html .= '</div>'; // .card-content
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
            $html .= '<div class="gc-fm-item gc-fm-item-folder gc-fm-item-parent" style="display:flex;align-items:center;padding:8px 12px;border-bottom:1px solid #e0e0e0;" data-path="' . htmlspecialchars($parentPath) . '">';
            $html .= '<i class="bi bi-arrow-return-left grey-text" style="margin-right:12px;font-size:1.2rem;"></i>';
            $html .= '<div style="flex:1;"><span style="font-weight:500;color:#1565c0;">' . $lblParent . '</span></div>';
            $html .= '<div class="grey-text" style="font-size:0.85rem;min-width:80px;text-align:right;">-</div>';
            $html .= '<div class="grey-text" style="font-size:0.85rem;min-width:150px;text-align:right;">-</div>';
            $html .= '<div style="min-width:100px;text-align:right;"></div>';
            $html .= '</div>';
        }

        // Header
        $html .= '<div class="gc-fm-item gc-fm-header" style="display:flex;align-items:center;padding:8px 12px;border-bottom:1px solid #e0e0e0;background:#f5f5f5;font-weight:600;font-size:0.8rem;text-transform:uppercase;color:#9e9e9e;">';
        $html .= '<div class="gc-fm-col-icon" style="width:32px;"></div>';
        $html .= '<div style="flex:1;">' . $lblName . '</div>';
        $html .= '<div style="min-width:80px;text-align:right;">' . $lblSize . '</div>';
        $html .= '<div style="min-width:150px;text-align:right;">' . $lblModified . '</div>';
        $html .= '<div style="min-width:100px;text-align:center;">' . $lblActions . '</div>';
        $html .= '</div>';

        if (empty($folders) && empty($files)) {
            $html .= '<div style="text-align:center;color:#9e9e9e;padding:32px 0;"><i class="bi bi-folder2-open" style="display:block;margin-bottom:8px;font-size:2.5rem;opacity:0.3;"></i>' . $lblNoFiles . '</div>';
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

        $iconColor = $isDir ? '#f9a825' : '#9e9e9e';
        $itemClass = $isDir ? 'gc-fm-item-folder' : 'gc-fm-item-file';
        $clickable = $isDir ? ' gc-fm-item-clickable' : '';

        $lblRename  = $lang['file_manager_rename'] ?? 'Rename';
        $lblDelete  = $lang['file_manager_delete'] ?? 'Delete';
        $lblMove    = $lang['file_manager_move'] ?? 'Move';
        $lblCopy    = $lang['file_manager_copy'] ?? 'Copy';
        $lblDownload = $lang['file_manager_download'] ?? 'Download';
        $lblPreview = $lang['file_manager_preview'] ?? 'Preview';

        $html = '<div class="gc-fm-item ' . $itemClass . $clickable . '" style="display:flex;align-items:center;padding:8px 12px;border-bottom:1px solid #f0f0f0;" data-path="' . $path . '" data-name="' . $name . '" data-isdir="' . ($isDir ? '1' : '0') . '" data-ext="' . $ext . '">';

        // Icon
        $html .= '<div class="gc-fm-col-icon" style="width:24px;text-align:center;margin-right:12px;color:' . $iconColor . ';"><i class="bi ' . $icon . '"></i></div>';

        // Name
        $html .= '<div style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">';
        if ($isDir) {
            $html .= '<a href="#" style="text-decoration:none;color:#333;font-weight:500;" class="gc-fm-folder-link">' . $name . '</a>';
        } else {
            if ($isImage) {
                $html .= '<a href="' . $url . '" style="text-decoration:none;color:#333;" class="gc-fm-image-link" target="_blank" data-url="' . $url . '">' . $name . '</a>';
            } else {
                $html .= '<span style="color:#333;">' . $name . '</span>';
            }
        }
        $html .= '</div>';

        // Size
        $html .= '<div class="grey-text" style="font-size:0.85rem;min-width:80px;text-align:right;white-space:nowrap;">' . $size . '</div>';

        // Modified
        $html .= '<div class="grey-text" style="font-size:0.85rem;min-width:150px;text-align:right;white-space:nowrap;">' . $modified . '</div>';

        // Actions dropdown
        $html .= '<div style="min-width:100px;text-align:center;white-space:nowrap;">';
        if ($writable) {
            $html .= '<div style="display:inline-block;position:relative;">';
            $html .= '<a class="btn-small waves-effect waves-light grey lighten-3 black-text dropdown-trigger" href="#" data-target="' . uniqid('fm_actions_') . '" style="padding:0 6px;"><i class="bi bi-three-dots-vertical"></i></a>';
            $html .= '<ul style="display:none;" class="gc-fm-actions-menu">';

            if ($isDir) {
                $html .= '<li><a class="gc-fm-action-rename" href="#"><i class="bi bi-pencil" style="margin-right:8px;"></i>' . $lblRename . '</a></li>';
                $html .= '<li><a class="gc-fm-action-delete" href="#"><i class="bi bi-trash" style="margin-right:8px;color:#e53935;"></i>' . $lblDelete . '</a></li>';
            } else {
                $html .= '<li><a class="gc-fm-action-download" href="' . $url . '" download><i class="bi bi-download" style="margin-right:8px;"></i>' . $lblDownload . '</a></li>';
                if ($isImage) {
                    $html .= '<li><a class="gc-fm-action-preview" href="#" data-url="' . $url . '"><i class="bi bi-eye" style="margin-right:8px;"></i>' . $lblPreview . '</a></li>';
                }
                $html .= '<li class="divider"></li>';
                $html .= '<li><a class="gc-fm-action-rename" href="#"><i class="bi bi-pencil" style="margin-right:8px;"></i>' . $lblRename . '</a></li>';
                $html .= '<li><a class="gc-fm-action-move" href="#"><i class="bi bi-arrows-move" style="margin-right:8px;"></i>' . $lblMove . '</a></li>';
                $html .= '<li><a class="gc-fm-action-copy" href="#"><i class="bi bi-files" style="margin-right:8px;"></i>' . $lblCopy . '</a></li>';
                $html .= '<li class="divider"></li>';
                $html .= '<li><a class="gc-fm-action-delete" href="#"><i class="bi bi-trash" style="margin-right:8px;color:#e53935;"></i>' . $lblDelete . '</a></li>';
            }

            $html .= '</ul></div>';
        } else {
            if (!$isDir) {
                $html .= '<a href="' . $url . '" class="btn-small waves-effect waves-light grey lighten-3 black-text" download style="padding:0 6px;"><i class="bi bi-download"></i></a>';
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

        $html = '<ul class="gc-fm-tree-children" style="list-style:none;padding-left:' . ($depth * 16) . 'px;">';
        foreach ($tree as $node) {
            $name = htmlspecialchars($node['name']);
            $path = htmlspecialchars($node['path']);
            $hasChildren = !empty($node['children']);
            $isActive = $path === $currentPath;

            $html .= '<li class="gc-fm-tree-item" data-path="' . $path . '">';
            $html .= '<a href="#" style="display:flex;align-items:center;padding:4px 8px;border-radius:4px;text-decoration:none;font-size:0.9rem;color:' . ($isActive ? '#1565c0' : '#333') . ';background:' . ($isActive ? '#e3f2fd' : 'transparent') . ';">';
            if ($hasChildren) {
                $html .= '<i class="bi bi-chevron-right gc-fm-tree-toggle" style="margin-right:4px;font-size:0.65rem;"></i>';
            } else {
                $html .= '<span style="margin-right:4px;width:0.65rem;"></span>';
            }
            $html .= '<i class="bi bi-folder" style="color:#f9a825;margin-right:4px;"></i>';
            $html .= '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $name . '</span>';
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
                $html .= '<a href="#" class="gc-fm-breadcrumb-link" style="text-decoration:none;color:#757575;" data-path="' . $path . '">' . $name . '</a>';
                $html .= '<span style="color:#bdbdbd;">&rsaquo;</span>';
            } else {
                $html .= '<span style="font-weight:600;color:#333;">' . $name . '</span>';
            }
        }

        return $html;
    }
}
