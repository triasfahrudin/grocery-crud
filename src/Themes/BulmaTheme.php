<?php

declare(strict_types=1);

namespace GroceryCrud\Themes;

class BulmaTheme implements ThemeInterface
{
    private array $languageStrings = [];

    public function setLanguageStrings(array $strings): void
    {
        $this->languageStrings = $strings;
    }

    public function getName(): string
    {
        return 'bulma';
    }

    public function getCssFiles(): array
    {
        return [
            'https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css',
        ];
    }

    public function getJsFiles(): array
    {
        return [
            'https://code.jquery.com/jquery-3.7.1.min.js',
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
        $html .= '<header class="card-header">';
        $html .= '<p class="card-header-title"><i class="fas fa-table mr-2"></i>' . $subject . '</p>';
        $html .= '<div class="card-header-icon">';
        $html .= '<div class="buttons are-small">';

        if ($enableExport && !empty($exportFormats)) {
            $html .= '<div class="dropdown is-right">';
            $html .= '<div class="dropdown-trigger">';
            $html .= '<button class="button is-small is-outlined" type="button" data-bs-toggle="dropdown">';
            $html .= '<i class="fas fa-download mr-1"></i>' . $lblExport;
            $html .= '</button></div>';
            $html .= '<div class="dropdown-menu" role="menu">';
            $html .= '<div class="dropdown-content">';
            foreach ($exportFormats as $format) {
                $label = $format === 'csv' ? $lblExportCsv : $lblExportExcel;
                $html .= '<a href="#" class="dropdown-item" data-export="' . $format . '">' . $label . '</a>';
            }
            $html .= '</div></div></div>';
        }

        if ($enableFilters) {
            $html .= '<button type="button" class="button is-small is-outlined gc-tool-btn gc-filter-btn" title="' . ($lang['filters'] ?? 'Filters') . '">';
            $html .= '<i class="fas fa-filter"></i></button>';
        }

        if ($enableColumns) {
            $html .= '<div class="dropdown is-right">';
            $html .= '<div class="dropdown-trigger">';
            $html .= '<button type="button" class="button is-small is-outlined gc-tool-btn gc-btn-columns" title="' . ($lang['columns'] ?? 'Columns') . '">';
            $html .= '<i class="fas fa-columns"></i></button></div>';
            $html .= '<div class="dropdown-menu gc-columns-menu" style="min-width:200px"><div class="dropdown-content p-2"></div></div>';
            $html .= '</div>';
        }

        if ($enableSettings) {
            $html .= '<div class="dropdown is-right">';
            $html .= '<div class="dropdown-trigger">';
            $html .= '<button type="button" class="button is-small is-outlined gc-tool-btn gc-btn-settings" title="' . ($lang['settings'] ?? 'Settings') . '">';
            $html .= '<i class="fas fa-cog"></i></button></div>';
            $html .= '<div class="dropdown-menu gc-settings-menu"><div class="dropdown-content">';
            $html .= '<a class="dropdown-item gc-settings-save" href="#"><i class="fas fa-save mr-2"></i>' . ($lang['save_settings'] ?? 'Save') . '</a>';
            $html .= '<a class="dropdown-item gc-settings-load" href="#"><i class="fas fa-undo mr-2"></i>' . ($lang['load_settings'] ?? 'Load') . '</a>';
            $html .= '<hr class="dropdown-divider">';
            $html .= '<a class="dropdown-item gc-settings-reset" href="#"><i class="fas fa-trash mr-2"></i>' . ($lang['reset_settings'] ?? 'Reset') . '</a>';
            $html .= '</div></div></div>';
        }

        if ($softDelete) {
            if ($trashedView) {
                $html .= '<button type="button" class="button is-small is-outlined gc-tool-btn gc-btn-active" title="' . ($lang['active_list'] ?? 'Active Records') . '">';
                $html .= '<i class="fas fa-list mr-1"></i>' . ($lang['active_list'] ?? 'Active') . '</button>';
            } else {
                $html .= '<button type="button" class="button is-small is-outlined gc-tool-btn gc-btn-trash" title="' . ($lang['trash_list'] ?? 'Trash') . '">';
                $html .= '<i class="fas fa-trash mr-1"></i>' . ($lang['trash_list'] ?? 'Trash') . '</button>';
            }
        }

        if ($hasAdd) {
            $html .= '<button type="button" class="button is-small is-primary btn-gc-add">';
            $html .= '<i class="fas fa-plus mr-1"></i>' . $lblAddRecord . '</button>';
        }

        $html .= '</div></div></header>';
        $html .= '<div class="card-content"><div class="content">';

        // Filter panel
        if ($enableFilters) {
            $html .= '<div class="gc-filter-panel box mb-4" style="display:none">';
            $html .= '<div class="gc-filter-rows">';
            $html .= '<div class="gc-filter-item gc-filter-item-template columns is-variable is-2 is-mobile mb-2" style="display:none">';
            $html .= '<div class="column"><div class="select is-small is-fullwidth"><select class="gc-filter-col"><option value="">' . ($lang['select_column'] ?? 'Column') . '</option></select></div></div>';
            $html .= '<div class="column"><div class="select is-small is-fullwidth"><select class="gc-filter-op">';
            $html .= '<option value="contains">' . ($lang['contains'] ?? 'Contains') . '</option>';
            $html .= '<option value="equals">' . ($lang['equals'] ?? 'Equals') . '</option>';
            $html .= '<option value="not_equal">' . ($lang['not_equal'] ?? 'Not equal') . '</option>';
            $html .= '<option value="starts_with">' . ($lang['starts_with'] ?? 'Starts with') . '</option>';
            $html .= '<option value="ends_with">' . ($lang['ends_with'] ?? 'Ends with') . '</option>';
            $html .= '<option value="greater_than">' . ($lang['greater_than'] ?? 'Greater than') . '</option>';
            $html .= '<option value="less_than">' . ($lang['less_than'] ?? 'Less than') . '</option>';
            $html .= '</select></div></div>';
            $html .= '<div class="column"><input type="text" class="input is-small gc-filter-val" placeholder="' . ($lang['value'] ?? 'Value') . '"></div>';
            $html .= '<div class="column is-narrow"><button type="button" class="delete gc-filter-item-remove" title="' . ($lang['remove'] ?? 'Remove') . '"></button></div>';
            $html .= '</div></div>';
            $html .= '<div class="gc-filter-actions mt-2">';
            $html .= '<button type="button" class="button is-small is-outlined is-primary gc-filter-add">+ ' . ($lang['add_filter'] ?? 'Add Filter') . '</button> ';
            $html .= '<button type="button" class="button is-small is-primary gc-filter-apply">' . ($lang['apply'] ?? 'Apply') . '</button> ';
            $html .= '<button type="button" class="button is-small is-outlined gc-filter-clear">' . ($lang['clear'] ?? 'Clear') . '</button>';
            $html .= '</div></div>';
        }

        // Search bar + batch
        $html .= '<div class="columns is-vcentered mb-4">';
        if ($searchable) {
            $html .= '<div class="column">';
            $html .= '<div class="field has-addons">';
            $html .= '<div class="control is-expanded">';
            $html .= '<input type="text" class="input is-small gc-search-input" placeholder="' . $lblSearch . '...">';
            $html .= '</div>';
            $html .= '<div class="control"><button class="button is-small gc-search-clear" type="button" style="display:none"><i class="fas fa-times"></i></button></div>';
            $html .= '<div class="control"><button class="button is-small is-outlined gc-search-btn" type="button"><i class="fas fa-search"></i></button></div>';
            $html .= '</div></div>';
        } else {
            $html .= '<div class="column"></div>';
        }

        if ($hasBatch) {
            $html .= '<div class="column is-narrow"><div class="gc-batch-toolbar field is-grouped is-grouped-multiline" style="display:none">';
            $html .= '<div class="control"><span class="tags has-addons"><span class="tag is-dark gc-selected-num">0</span><span class="tag">' . $lblRecords . '</span></span></div>';
            foreach ($batchActions as $actionId => $label) {
                $color = match ($actionId) {
                    'delete_selected'  => 'is-danger',
                    'restore_selected' => 'is-success',
                    default            => 'is-outlined',
                };
                $html .= '<div class="control"><button type="button" class="button is-small ' . $color . ' gc-batch-action" data-batch-action="' . $actionId . '">' . htmlspecialchars($label) . '</button></div>';
            }
            $html .= '</div></div>';
        }
        $html .= '</div>';

        // Table
        $html .= '<div class="table-container">';
        $html .= '<table class="table is-fullwidth is-hoverable is-bordered gc-table" data-crud-id="' . $crudId . '">';
        $html .= '<thead>';
        $html .= '<tr>';
        if ($hasSubGrid) {
            $html .= '<th class="has-text-centered" style="width:40px"><i class="fas fa-chevron-circle-down"></i></th>';
        }
        if ($hasBatch) {
            $html .= '<th class="has-text-centered" style="width:40px"><input type="checkbox" class="gc-select-all" title="' . $lblSelectAll . '"></th>';
        }
        foreach ($columns as $col) {
            $label = $columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
            $isSorted = $col === $sortField;
            $dir = $isSorted ? $sortDir : 'ASC';
            $nextDir = $isSorted && $dir === 'ASC' ? 'DESC' : 'ASC';
            $arrow = $isSorted ? ($dir === 'ASC' ? ' &#9650;' : ' &#9660;') : '';
            $html .= '<th class="is-narrow gc-sortable" data-column="' . $col . '" data-label="' . htmlspecialchars($label) . '" data-sort-field="' . $col . '" data-sort-dir="' . $nextDir . '">' . htmlspecialchars($label) . $arrow . '</th>';
        }
        if ($showActions || !empty($customActions)) {
            $html .= '<th class="has-text-centered" style="width:120px">' . $lblActions . '</th>';
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
                $html .= '<td>';
                if (isset($columnFilters[$col])) {
                    $filterDef = $columnFilters[$col];
                    $filterType = $filterDef['type'] ?? 'text';
                    $filterOptions = $filterDef['options'] ?? [];
                    $currentVal = $currentFilters[$col] ?? '';
                    if ($filterType === 'dropdown') {
                        $html .= '<div class="select is-small is-fullwidth">';
                        $html .= '<select class="gc-column-filter" data-filter-field="' . $col . '">';
                        $html .= '<option value="">' . $lblAll . '</option>';
                        foreach ($filterOptions as $optValue => $optLabel) {
                            $selected = (string) $currentVal === (string) $optValue ? ' selected' : '';
                            $html .= '<option value="' . htmlspecialchars((string) $optValue) . '"' . $selected . '>' . htmlspecialchars((string) $optLabel) . '</option>';
                        }
                        $html .= '</select></div>';
                    } else {
                        $html .= '<input type="text" class="input is-small gc-column-filter" data-filter-field="' . $col . '" placeholder="' . $lblFilter . '" value="' . htmlspecialchars((string) $currentVal) . '">';
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
            $html .= '<tr><td colspan="' . $colspan . '" class="has-text-centered has-text-grey-light py-5">' . $lblNoRecords . '</td></tr>';
        } else {
            foreach ($records as $row) {
                $rowId = htmlspecialchars((string) ($row[$primaryKey] ?? ''));
                $trashedClass = $trashedView ? ' class="gc-trashed"' : '';
                $html .= '<tr' . $trashedClass . ' data-parent-id="' . $rowId . '">';
                if ($hasSubGrid) {
                    $sgField = array_key_first($subGrids);
                    $html .= '<td class="has-text-centered">';
                    $html .= '<button type="button" class="button is-small is-outlined gc-subgrid-toggle" data-subgrid="' . htmlspecialchars($sgField) . '" data-parent-id="' . $rowId . '" title="Expand">';
                    $html .= '<i class="fas fa-chevron-right"></i></button></td>';
                }
                if ($hasBatch) {
                    $html .= '<td class="has-text-centered"><input type="checkbox" class="gc-row-checkbox" value="' . $rowId . '"></td>';
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
                    $html .= '<td class="has-text-centered is-nowrap">';
                    $html .= '<div class="buttons are-small is-centered">';
                    if ($trashedView) {
                        $html .= '<button type="button" class="button is-small is-success is-outlined btn-gc-restore" data-id="' . $rowId . '" title="' . ($lang['restore'] ?? 'Restore') . '">';
                        $html .= '<i class="fas fa-undo"></i></button>';
                    }
                    if ($hasEdit) {
                        $html .= '<button type="button" class="button is-small is-primary is-outlined btn-gc-edit" data-id="' . $rowId . '" title="' . $lblEdit . '">';
                        $html .= '<i class="fas fa-pen"></i></button>';
                    }
                    foreach ($customActions as $action) {
                        $rowId = (string) ($row[$primaryKey] ?? '');
                        $url = str_replace('{id}', $rowId, $action['url'] ?? '#');
                        $actionLabel = $action['label'] ?? '';
                        $actionIcon = $action['icon'] ?? '';
                        $html .= '<a href="' . $url . '" class="button is-small is-outlined" title="' . htmlspecialchars($actionLabel) . '">';
                        if ($actionIcon !== '') {
                            $html .= '<i class="' . htmlspecialchars($actionIcon) . '"></i>';
                        } else {
                            $html .= htmlspecialchars($actionLabel);
                        }
                        $html .= '</a>';
                    }
                    if ($hasDelete) {
                        $html .= '<button type="button" class="button is-small is-danger is-outlined btn-gc-delete" data-id="' . $rowId . '" title="' . $lblDelete . '">';
                        $html .= '<i class="fas fa-trash"></i></button>';
                    }
                    $html .= '</div></td>';
                }
                $html .= '</tr>';

                // Sub-grid row
                if ($hasSubGrid) {
                    $sgField = array_key_first($subGrids);
                    $sgConfig = $subGrids[$sgField];
                    $sgColspan = $colspan;
                    $html .= '<tr class="gc-subgrid-row" style="display:none" data-parent-id="' . $rowId . '">';
                    $html .= '<td colspan="' . $sgColspan . '">';
                    $html .= '<div class="gc-subgrid-content" data-subgrid="' . htmlspecialchars($sgField) . '">';
                    $html .= '<div class="gc-loading-sub has-text-grey-light"><i class="fas fa-spinner fa-pulse mr-1"></i> Memuat data...</div>';
                    $html .= '</div></td></tr>';
                }
            }
        }
        $html .= '</tbody></table></div>';

        // Pagination
        if ($totalPages > 1 && $pager !== null) {
            $from = $pager['from'] ?? 0;
            $to   = $pager['to'] ?? 0;
            $html .= '<div class="columns is-vcentered mt-3">';
            $html .= '<div class="column"><span class="has-text-grey is-size-7">' . $from . '&ndash;' . $to . ' ' . $lblOf . ' ' . $totalCount . ' ' . $lblRecords . '</span></div>';
            $html .= '<div class="column is-narrow"><nav class="pagination is-small mb-0">';
            $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
            $html .= '<a class="pagination-previous gc-page-link"' . $prevDisabled . ' href="#" data-page="' . ($currentPage - 1) . '">' . $lblPrevious . '</a>';

            $html .= '<ul class="pagination-list">';
            $startPage = max(1, $currentPage - 2);
            $endPage   = min($totalPages, $currentPage + 2);
            if ($startPage > 1) {
                $html .= '<li><a class="pagination-link gc-page-link" href="#" data-page="1">1</a></li>';
                if ($startPage > 2) {
                    $html .= '<li><span class="pagination-ellipsis">&hellip;</span></li>';
                }
            }
            for ($i = $startPage; $i <= $endPage; $i++) {
                $active = $i === $currentPage ? ' is-current' : '';
                $html .= '<li><a class="pagination-link gc-page-link' . $active . '" href="#" data-page="' . $i . '">' . $i . '</a></li>';
            }
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    $html .= '<li><span class="pagination-ellipsis">&hellip;</span></li>';
                }
                $html .= '<li><a class="pagination-link gc-page-link" href="#" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
            }
            $html .= '</ul>';

            $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
            $html .= '<a class="pagination-next gc-page-link"' . $nextDisabled . ' href="#" data-page="' . ($currentPage + 1) . '">' . $lblNext . '</a>';
            $html .= '</nav></div></div>';
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
        $modalIcon = $isEdit ? 'fas fa-pen' : 'fas fa-plus';

        $html = '<div class="grocery-crud-form-wrapper" id="' . $crudId . '_form">';
        $html .= '<div class="card">';
        $html .= '<header class="card-header">';
        $html .= '<p class="card-header-title"><i class="' . $modalIcon . ' mr-2"></i>' . $lblTitle . '</p>';
        $html .= '<button type="button" class="card-header-icon gc-form-close" aria-label="' . $lblCancel . '">';
        $html .= '<span class="delete"></span></button>';
        $html .= '</header>';
        $html .= '<div class="card-content">';
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

            $html .= '<div class="field">';
            $html .= '<label class="label" for="gc_field_' . $field . '">';
            $html .= htmlspecialchars($label);
            if ($isRequired) {
                $html .= ' <span class="has-text-danger">*</span>';
            }
            $html .= '</label>';
            $html .= '<div class="control">';
            $html .= $this->renderFormField($field, $type, $value, $options, $isReadonly, $isUpload, $data);
            $html .= '</div>';
            if ($fieldError) {
                $html .= '<p class="help is-danger">' . htmlspecialchars($fieldError) . '</p>';
            }
            $html .= '</div>';
        }
        $html .= '</form></div>';
        $html .= '<footer class="card-footer">';
        $html .= '<div class="card-footer-item">';
        $html .= '<div class="buttons">';
        $html .= '<button type="submit" class="button is-primary"><i class="fas fa-check mr-1"></i>' . $lblSave . '</button>';
        $html .= '<button type="button" class="button gc-form-close"><i class="fas fa-times mr-1"></i>' . $lblCancel . '</button>';
        $html .= '</div></div></footer>';
        $html .= '</div></div>';
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
                $html .= '<textarea class="textarea" id="' . $fieldId . '" name="' . $fieldName . '" rows="4"' . $readonlyAttr . '>' . htmlspecialchars((string) $value) . '</textarea>';
                break;
            case 'hidden':
                $html .= '<input type="hidden" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '">';
                break;
            case 'integer':
            case 'numeric':
                $html .= '<input type="number" step="any" class="input" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'date':
                $html .= '<input type="date" class="input" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'datetime':
                $html .= '<input type="datetime-local" class="input" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'time':
                $html .= '<input type="time" class="input" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'email':
                $html .= '<input type="email" class="input" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'url':
                $html .= '<input type="url" class="input" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'phone':
                $html .= '<input type="tel" class="input" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'color':
                $html .= '<input type="color" class="input" style="height:2.5em;padding:2px" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'password':
                $html .= '<input type="password" class="input" id="' . $fieldId . '" name="' . $fieldName . '"' . $readonlyAttr . '>';
                if (!empty($value)) {
                    $html .= '<p class="help">Leave empty to keep current password.</p>';
                }
                break;
            case 'true_false':
            case 'boolean':
                $checked = !empty($value) ? ' checked' : '';
                $html .= '<input type="checkbox" class="switch is-rounded" id="' . $fieldId . '" name="' . $fieldName . '" value="1"' . $checked . $readonlyAttr . '>';
                break;
            case 'dropdown':
            case 'enum':
            case 'relation':
                $html .= '<div class="select is-fullwidth">';
                $html .= '<select id="' . $fieldId . '" name="' . $fieldName . '"' . ($isReadonly ? ' disabled' : '') . '>';
                $html .= '<option value="">-- Select --</option>';
                foreach ($options as $optValue => $optLabel) {
                    $selected = ((string) $optValue === (string) $value) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars((string) $optValue) . '"' . $selected . '>' . htmlspecialchars((string) $optLabel) . '</option>';
                }
                $html .= '</select></div>';
                if ($isReadonly) {
                    $html .= '<input type="hidden" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '">';
                }
                break;
            case 'set':
            case 'n_to_n':
                $html .= '<div class="box" style="max-height:200px;overflow-y:auto;padding:0.5rem">';
                if (!empty($options) && is_array($options)) {
                    $selectedValues = is_array($value) ? $value : explode(',', (string) $value);
                    foreach ($options as $optValue => $optLabel) {
                        $checked = in_array((string) $optValue, $selectedValues, true) ? ' checked' : '';
                        $optId = $fieldId . '_' . md5((string) $optValue);
                        $html .= '<label class="checkbox" for="' . $optId . '">';
                        $html .= '<input type="checkbox" id="' . $optId . '" name="' . $fieldName . '[]" value="' . htmlspecialchars((string) $optValue) . '"' . $checked . ($isReadonly ? ' disabled' : '') . '> ';
                        $html .= htmlspecialchars((string) $optLabel);
                        $html .= '</label><br>';
                    }
                }
                $html .= '</div>';
                break;
            case 'image':
            case 'file':
                $html .= '<div class="file">';
                $html .= '<label class="file-label">';
                $html .= '<input type="file" class="file-input" id="' . $fieldId . '" name="' . $fieldName . '"' . $readonlyAttr . '>';
                $html .= '<span class="file-cta"><span class="file-icon"><i class="fas fa-upload"></i></span><span class="file-label">Choose file...</span></span>';
                $html .= '</label></div>';
                if (!empty($value)) {
                    $html .= '<div class="mt-2">';
                    if ($type === 'image') {
                        $html .= '<img src="' . htmlspecialchars((string) $value) . '" class="image" style="max-height:100px" alt="">';
                    } else {
                        $html .= '<a href="' . htmlspecialchars((string) $value) . '" target="_blank" class="button is-small is-outlined">';
                        $html .= '<i class="fas fa-paperclip"></i> ' . basename((string) $value) . '</a>';
                    }
                    $html .= '</div>';
                    $html .= '<input type="hidden" name="' . $fieldName . '_existing" value="' . htmlspecialchars(basename((string) $value)) . '">';
                }
                break;
            case 'read_only':
                $html .= '<input type="text" class="input" id="' . $fieldId . '" value="' . htmlspecialchars((string) $value) . '" readonly disabled>';
                break;
            case 'repeater':
                if ($rDef === null) break;
                $repeatables = $rDef['repeatables'] ?? [];
                $html .= '<div class="gc-repeater-container box">';
                foreach ($rValues as $rIndex => $rItem) {
                    $html .= '<div class="gc-repeater-item box mb-3">';
                    $html .= '<div class="level-right mb-2">';
                    $html .= '<button type="button" class="delete gc-repeater-remove"></button>';
                    $html .= '</div>';
                    foreach ($repeatables as $subField) {
                        $sfName  = $subField['name'];
                        $sfLabel = $subField['label'] ?? ucfirst($sfName);
                        $sfType  = $subField['type'] ?? 'text';
                        $sfOpts  = $subField['options'] ?? [];
                        $sfValue = $rItem[$sfName] ?? '';
                        $inputName = $fieldName . '[' . $rIndex . '][' . $sfName . ']';
                        $inputId   = $fieldId . '_' . $rIndex . '_' . $sfName;
                        $html .= '<div class="field">';
                        $html .= '<label class="label is-small" for="' . $inputId . '">' . htmlspecialchars($sfLabel) . '</label>';
                        $html .= '<div class="control">';
                        $html .= $this->renderRepeaterSubField($inputName, $inputId, $sfType, $sfValue, $sfOpts);
                        $html .= '</div></div>';
                    }
                    $html .= '</div>';
                }
                $template = '<div class="gc-repeater-item box mb-3">';
                $template .= '<div class="level-right mb-2">';
                $template .= '<button type="button" class="delete gc-repeater-remove"></button>';
                $template .= '</div>';
                foreach ($repeatables as $subField) {
                    $sfName  = $subField['name'];
                    $sfLabel = $subField['label'] ?? ucfirst($sfName);
                    $sfType  = $subField['type'] ?? 'text';
                    $sfOpts  = $subField['options'] ?? [];
                    $inputName = $fieldName . '[__INDEX__][' . $sfName . ']';
                    $inputId   = $fieldId . '__INDEX__' . $sfName;
                    $template .= '<div class="field">';
                    $template .= '<label class="label is-small" for="' . $inputId . '">' . htmlspecialchars($sfLabel) . '</label>';
                    $template .= '<div class="control">';
                    $template .= $this->renderRepeaterSubField($inputName, $inputId, $sfType, '', $sfOpts, true);
                    $template .= '</div></div>';
                }
                $template .= '</div>';
                $html .= '<div class="gc-repeater-template" style="display:none">' . $template . '</div>';
                $html .= '<button type="button" class="button is-small is-outlined is-primary mt-2 gc-repeater-add"><i class="fas fa-plus"></i> Add Item</button>';
                $html .= '</div>';
                break;
            default:
                $html .= '<input type="text" class="input" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
        }
        return $html;
    }

    private function renderRepeaterSubField(string $name, string $id, string $type, mixed $value, array $options = [], bool $disabled = false): string
    {
        $d = $disabled ? ' disabled' : '';
        switch ($type) {
            case 'textarea':
                return '<textarea class="textarea is-small" id="' . $id . '" name="' . $name . '" rows="2"' . $d . '>' . htmlspecialchars((string) $value) . '</textarea>';
            case 'integer':
            case 'numeric':
                return '<input type="number" step="any" class="input is-small" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';
            case 'select':
            case 'dropdown':
                $html = '<div class="select is-small is-fullwidth"><select id="' . $id . '" name="' . $name . '"' . $d . '>';
                $html .= '<option value="">-- Select --</option>';
                foreach ($options as $optValue => $optLabel) {
                    $selected = ((string) $optValue === (string) $value) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars((string) $optValue) . '"' . $selected . '>' . htmlspecialchars((string) $optLabel) . '</option>';
                }
                $html .= '</select></div>';
                return $html;
            case 'boolean':
            case 'true_false':
                $checked = !empty($value) ? ' checked' : '';
                return '<input type="checkbox" class="switch is-rounded is-small" id="' . $id . '" name="' . $name . '" value="1"' . $checked . $d . '>';
            case 'hidden':
                return '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';
            default:
                return '<input type="text" class="input is-small" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';
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
        $html .= '<div class="box p-0">';
        $html .= '<div class="level px-4 py-2" style="border-bottom:1px solid #dbdbdb;background:#f5f5f5">';
        $html .= '<div class="level-left"><div class="level-item"><span class="has-text-weight-semibold"><i class="fas fa-th mr-2"></i>' . htmlspecialchars($tableLabel) . '</span></div></div>';
        $html .= '<div class="level-right"><div class="level-item"><span class="tag is-info is-light">' . $recordCount . ' data</span></div></div>';
        $html .= '</div>';
        $html .= '<div class="table-container">';
        $html .= '<table class="table is-fullwidth is-hoverable is-bordered gc-subgrid-table">';
        $html .= '<thead><tr>';
        foreach ($columns as $col) {
            $label = $columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
            $html .= '<th>' . htmlspecialchars($label) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if (empty($records)) {
            $colspan = count($columns);
            $html .= '<tr><td colspan="' . $colspan . '" class="has-text-centered has-text-grey-light py-5">Tidak ada data terkait.</td></tr>';
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
}
