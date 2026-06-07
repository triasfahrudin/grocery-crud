<?php

declare(strict_types=1);

namespace GroceryCrud\Themes;

class TailwindTheme implements ThemeInterface
{
    private array $languageStrings = [];

    public function setLanguageStrings(array $strings): void
    {
        $this->languageStrings = $strings;
    }

    public function getName(): string
    {
        return 'tailwind';
    }

    public function getCssFiles(): array
    {
        return [
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        ];
    }

    public function getJsFiles(): array
    {
        return [
            'https://cdn.tailwindcss.com',
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
        $html .= '<div class="bg-white shadow-sm rounded-lg border border-gray-200 mb-4">';
        $html .= '<div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">';
        $html .= '<h5 class="text-lg font-semibold text-gray-800"><i class="bi bi-table mr-2"></i>' . $subject . '</h5>';
        $html .= '<div class="flex gap-2 items-center">';

        if ($enableExport && !empty($exportFormats)) {
            $html .= '<div class="relative">';
            $html .= '<button class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 dropdown-toggle" type="button" data-bs-toggle="dropdown">';
            $html .= '<i class="bi bi-download mr-1"></i>' . $lblExport;
            $html .= '</button>';
            $html .= '<ul class="dropdown-menu absolute right-0 mt-1 bg-white border rounded shadow-lg py-1 z-50 hidden">';
            foreach ($exportFormats as $format) {
                $label = match ($format) {
                    'csv'       => $lblExportCsv,
                    'excel'     => $lblExportExcel,
                    'pdf'       => $lblExportPdf,
                    'print'     => $lblPrintView,
                    default     => ucfirst($format),
                };
                $html .= '<li><a class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" href="#" data-export="' . $format . '">' . $label . '</a></li>';
            }
            $html .= '</ul></div>';
        }

        if ($enableFilters) {
            $html .= '<button type="button" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 gc-tool-btn gc-filter-btn" title="' . ($lang['filters'] ?? 'Filters') . '">';
            $html .= '<i class="bi bi-funnel"></i></button>';
        }

        if ($enableColumns) {
            $html .= '<div class="relative">';
            $html .= '<button type="button" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 gc-tool-btn gc-btn-columns dropdown-toggle" title="' . ($lang['columns'] ?? 'Columns') . '" data-bs-toggle="dropdown">';
            $html .= '<i class="bi bi-layout-three-columns"></i></button>';
            $html .= '<div class="dropdown-menu absolute right-0 mt-1 bg-white border rounded shadow-lg p-2 z-50 hidden gc-columns-menu" style="min-width:200px"></div>';
            $html .= '</div>';
        }

        if ($enableSettings) {
            $html .= '<div class="relative">';
            $html .= '<button type="button" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 gc-tool-btn gc-btn-settings dropdown-toggle" title="' . ($lang['settings'] ?? 'Settings') . '" data-bs-toggle="dropdown">';
            $html .= '<i class="bi bi-gear"></i></button>';
            $html .= '<ul class="dropdown-menu absolute right-0 mt-1 bg-white border rounded shadow-lg py-1 z-50 hidden gc-settings-menu">';
            $html .= '<li><a class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gc-settings-save" href="#"><i class="bi bi-floppy mr-2"></i>' . ($lang['save_settings'] ?? 'Save') . '</a></li>';
            $html .= '<li><a class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gc-settings-load" href="#"><i class="bi bi-arrow-counterclockwise mr-2"></i>' . ($lang['load_settings'] ?? 'Load') . '</a></li>';
            $html .= '<li><hr class="my-1 border-t border-gray-200"></li>';
            $html .= '<li><a class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 gc-settings-reset" href="#"><i class="bi bi-trash mr-2"></i>' . ($lang['reset_settings'] ?? 'Reset') . '</a></li>';
            $html .= '</ul></div>';
        }

        if ($softDelete) {
            if ($trashedView) {
                $html .= '<button type="button" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 gc-tool-btn gc-btn-active" title="' . ($lang['active_list'] ?? 'Active Records') . '">';
                $html .= '<i class="bi bi-list-ul mr-1"></i>' . ($lang['active_list'] ?? 'Active') . '</button>';
            } else {
                $html .= '<button type="button" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 gc-tool-btn gc-btn-trash" title="' . ($lang['trash_list'] ?? 'Trash') . '">';
                $html .= '<i class="bi bi-trash mr-1"></i>' . ($lang['trash_list'] ?? 'Trash') . '</button>';
            }
        }

        if ($hasAdd) {
            $html .= '<button type="button" class="px-4 py-1.5 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700 btn-gc-add">';
            $html .= '<i class="bi bi-plus-lg mr-1"></i>' . $lblAddRecord . '</button>';
        }

        $html .= '</div></div>';
        $html .= '<div class="p-5">';

        // Filter panel
        if ($enableFilters) {
            $html .= '<div class="gc-filter-panel mb-4 p-4 bg-gray-50 border border-gray-200 rounded" style="display:none">';
            $html .= '<div class="gc-filter-rows">';
            $html .= '<div class="gc-filter-item gc-filter-item-template flex gap-2 items-end mb-2" style="display:none">';
            $html .= '<select class="border border-gray-300 rounded px-2 py-1.5 text-sm gc-filter-col" style="min-width:130px"><option value="">' . ($lang['select_column'] ?? 'Column') . '</option></select>';
            $html .= '<select class="border border-gray-300 rounded px-2 py-1.5 text-sm gc-filter-op" style="min-width:110px">';
            $html .= '<option value="contains">' . ($lang['contains'] ?? 'Contains') . '</option>';
            $html .= '<option value="equals">' . ($lang['equals'] ?? 'Equals') . '</option>';
            $html .= '<option value="not_equal">' . ($lang['not_equal'] ?? 'Not equal') . '</option>';
            $html .= '<option value="starts_with">' . ($lang['starts_with'] ?? 'Starts with') . '</option>';
            $html .= '<option value="ends_with">' . ($lang['ends_with'] ?? 'Ends with') . '</option>';
            $html .= '<option value="greater_than">' . ($lang['greater_than'] ?? 'Greater than') . '</option>';
            $html .= '<option value="less_than">' . ($lang['less_than'] ?? 'Less than') . '</option>';
            $html .= '</select>';
            $html .= '<input type="text" class="border border-gray-300 rounded px-2 py-1.5 text-sm gc-filter-val" placeholder="' . ($lang['value'] ?? 'Value') . '" style="min-width:150px">';
            $html .= '<button type="button" class="text-red-500 hover:text-red-700 gc-filter-item-remove" title="' . ($lang['remove'] ?? 'Remove') . '">&times;</button>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="gc-filter-actions mt-2">';
            $html .= '<button type="button" class="px-3 py-1.5 text-sm border border-blue-600 text-blue-600 rounded hover:bg-blue-50 gc-filter-add">+ ' . ($lang['add_filter'] ?? 'Add Filter') . '</button> ';
            $html .= '<button type="button" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 gc-filter-apply">' . ($lang['apply'] ?? 'Apply') . '</button> ';
            $html .= '<button type="button" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 gc-filter-clear">' . ($lang['clear'] ?? 'Clear') . '</button>';
            $html .= '</div></div>';
        }

        // Search bar + batch
        $html .= '<div class="flex items-center mb-4 gap-4">';
        $searchCol = $searchable ? 'flex-1' : 'flex-1';
        $html .= '<div class="' . $searchCol . '">';
        if ($searchable) {
            $html .= '<div class="flex">';
            $html .= '<input type="text" class="flex-1 border border-gray-300 rounded-l px-3 py-1.5 text-sm gc-search-input" placeholder="' . $lblSearch . '...">';
            $html .= '<button class="px-3 py-1.5 border border-gray-300 rounded-r bg-gray-50 hover:bg-gray-100 gc-search-clear" type="button" style="display:none" tabindex="-1"><i class="bi bi-x-lg text-xs"></i></button>';
            $html .= '<button class="px-3 py-1.5 bg-gray-100 border border-l-0 border-gray-300 rounded-r hover:bg-gray-200 gc-search-btn" type="button"><i class="bi bi-search"></i></button>';
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($hasBatch) {
            $html .= '<div class="gc-batch-toolbar flex items-center gap-2" style="display:none">';
            $html .= '<span class="px-2 py-1 text-xs font-medium bg-gray-200 text-gray-700 rounded gc-selected-count"><span class="gc-selected-num">0</span> ' . $lblRecords . '</span>';
            foreach ($batchActions as $actionId => $label) {
                $color = match ($actionId) {
                    'delete_selected'  => 'bg-red-600 text-white hover:bg-red-700',
                    'restore_selected' => 'bg-green-600 text-white hover:bg-green-700',
                    default            => 'border border-gray-300 text-gray-700 hover:bg-gray-50',
                };
                $html .= '<button type="button" class="px-3 py-1.5 text-sm rounded ' . $color . ' gc-batch-action" data-batch-action="' . $actionId . '">' . htmlspecialchars($label) . '</button>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        // Table
        $html .= '<div class="overflow-x-auto">';
        $html .= '<table class="w-full border-collapse gc-table" data-crud-id="' . $crudId . '">';
        $html .= '<thead>';
        $html .= '<tr class="bg-gray-100 border-b border-gray-200">';
        if ($hasSubGrid) {
            $html .= '<th class="text-center px-2 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider" style="width:40px"><i class="bi bi-chevron-expand"></i></th>';
        }
        if ($hasBatch) {
            $html .= '<th class="text-center px-2 py-2" style="width:40px"><input type="checkbox" class="gc-select-all" title="' . $lblSelectAll . '"></th>';
        }
        foreach ($columns as $col) {
            $label = $columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
            $isSorted = $col === $sortField;
            $dir = $isSorted ? $sortDir : 'ASC';
            $nextDir = $isSorted && $dir === 'ASC' ? 'DESC' : 'ASC';
            $arrow = $isSorted ? ($dir === 'ASC' ? ' &#9650;' : ' &#9660;') : '';
            $html .= '<th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase tracking-wider whitespace-nowrap gc-sortable cursor-pointer hover:bg-gray-200" data-column="' . $col . '" data-label="' . htmlspecialchars($label) . '" data-sort-field="' . $col . '" data-sort-dir="' . $nextDir . '">' . htmlspecialchars($label) . $arrow . '</th>';
        }
        if ($showActions || !empty($customActions)) {
            $html .= '<th class="px-3 py-2 text-center text-xs font-medium text-gray-600 uppercase tracking-wider whitespace-nowrap" style="width:120px">' . $lblActions . '</th>';
        }
        $html .= '</tr>';

        // Column filter row
        if (!empty($columnFilters)) {
            $html .= '<tr class="bg-gray-50">';
            if ($hasSubGrid) {
                $html .= '<td></td>';
            }
            if ($hasBatch) {
                $html .= '<td></td>';
            }
            foreach ($columns as $col) {
                $html .= '<td data-column="' . $col . '" class="px-1 py-1">';
                if (isset($columnFilters[$col])) {
                    $filterDef = $columnFilters[$col];
                    $filterType = $filterDef['type'] ?? 'text';
                    $filterOptions = $filterDef['options'] ?? [];
                    $currentVal = $currentFilters[$col] ?? '';
                    if ($filterType === 'dropdown') {
                        $html .= '<select class="border border-gray-300 rounded px-1 py-1 text-xs w-full gc-column-filter" data-filter-field="' . $col . '">';
                        $html .= '<option value="">' . $lblAll . '</option>';
                        foreach ($filterOptions as $optValue => $optLabel) {
                            $selected = (string) $currentVal === (string) $optValue ? ' selected' : '';
                            $html .= '<option value="' . htmlspecialchars((string) $optValue) . '"' . $selected . '>' . htmlspecialchars((string) $optLabel) . '</option>';
                        }
                        $html .= '</select>';
                    } else {
                        $html .= '<input type="text" class="border border-gray-300 rounded px-1 py-1 text-xs w-full gc-column-filter" data-filter-field="' . $col . '" placeholder="' . $lblFilter . '" value="' . htmlspecialchars((string) $currentVal) . '">';
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
            $html .= '<tr><td colspan="' . $colspan . '" class="text-center text-gray-500 py-8 text-sm">' . $lblNoRecords . '</td></tr>';
        } else {
            foreach ($records as $row) {
                $rowId = htmlspecialchars((string) ($row[$primaryKey] ?? ''));
                $trashedClass = $trashedView ? ' class="bg-red-50 gc-trashed"' : '';
                $html .= '<tr' . $trashedClass . ' class="border-b border-gray-100 hover:bg-blue-50 transition-colors" data-parent-id="' . $rowId . '">';
                if ($hasSubGrid) {
                    $sgField = array_key_first($subGrids);
                    $html .= '<td class="text-center px-2 py-2">';
                    $html .= '<button type="button" class="px-2 py-1 text-sm border border-gray-300 rounded hover:bg-gray-100 gc-subgrid-toggle" data-subgrid="' . htmlspecialchars($sgField) . '" data-parent-id="' . $rowId . '" title="Expand">';
                    $html .= '<i class="bi bi-chevron-right"></i></button></td>';
                }
                if ($hasBatch) {
                    $html .= '<td class="text-center px-2 py-2"><input type="checkbox" class="gc-row-checkbox" value="' . $rowId . '"></td>';
                }
                foreach ($columns as $col) {
                    $value = $row[$col] ?? '';
                    $displayValue = $value;
                    if (!empty($fieldOptions[$col]) && isset($fieldOptions[$col][$value])) {
                        $displayValue = $fieldOptions[$col][$value];
                    }
                    $html .= '<td class="px-3 py-2 text-sm text-gray-700" data-column="' . $col . '">' . $displayValue . '</td>';
                }
                if ($showActions || !empty($customActions)) {
                    $html .= '<td class="text-center px-2 py-2 whitespace-nowrap">';
                    $html .= '<div class="inline-flex gap-1">';
                    if ($trashedView) {
                        $html .= '<button type="button" class="px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded hover:bg-green-200 btn-gc-restore" data-id="' . $rowId . '" title="' . ($lang['restore'] ?? 'Restore') . '">';
                        $html .= '<i class="bi bi-arrow-counterclockwise"></i></button>';
                    }
                    if ($hasEdit) {
                        $html .= '<button type="button" class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded hover:bg-blue-200 btn-gc-edit" data-id="' . $rowId . '" title="' . $lblEdit . '">';
                        $html .= '<i class="bi bi-pencil"></i></button>';
                    }
                    foreach ($customActions as $action) {
                        $rowId = (string) ($row[$primaryKey] ?? '');
                        $url = str_replace('{id}', $rowId, $action['url'] ?? '#');
                        $actionLabel = $action['label'] ?? '';
                        $actionIcon = $action['icon'] ?? '';
                        $html .= '<a href="' . $url . '" class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200" title="' . htmlspecialchars($actionLabel) . '">';
                        if ($actionIcon !== '') {
                            $html .= '<i class="' . htmlspecialchars($actionIcon) . '"></i>';
                        } else {
                            $html .= htmlspecialchars($actionLabel);
                        }
                        $html .= '</a>';
                    }
                    if ($hasDelete) {
                        $html .= '<button type="button" class="px-2 py-1 text-xs font-medium text-red-700 bg-red-100 rounded hover:bg-red-200 btn-gc-delete" data-id="' . $rowId . '" title="' . $lblDelete . '">';
                        $html .= '<i class="bi bi-trash"></i></button>';
                    }
                    $html .= '</div></td>';
                }
                $html .= '</tr>';

                // Sub-grid row
                if ($hasSubGrid) {
                    $sgField = array_key_first($subGrids);
                    $sgConfig = $subGrids[$sgField];
                    $sgColspan = $colspan;
                    $html .= '<tr class="gc-subgrid-row bg-gray-50" style="display:none" data-parent-id="' . $rowId . '">';
                    $html .= '<td colspan="' . $sgColspan . '">';
                    $html .= '<div class="gc-subgrid-content p-4" data-subgrid="' . htmlspecialchars($sgField) . '">';
                    $html .= '<div class="gc-loading-sub text-gray-500 text-sm"><i class="bi bi-arrow-clockwise mr-1 animate-spin"></i> Memuat data...</div>';
                    $html .= '</div></td></tr>';
                }
            }
        }
        $html .= '</tbody></table></div>';

        // Pagination
        if ($totalPages > 1 && $pager !== null) {
            $from = $pager['from'] ?? 0;
            $to   = $pager['to'] ?? 0;
            $html .= '<div class="flex items-center justify-between mt-4">';
            $html .= '<div class="text-sm text-gray-500">' . $from . '&ndash;' . $to . ' ' . $lblOf . ' ' . $totalCount . ' ' . $lblRecords . '</div>';
            $html .= '<nav><ul class="inline-flex gap-1">';
            $prevDisabled = $currentPage <= 1 ? ' opacity-50 pointer-events-none' : '';
            $html .= '<li class="' . $prevDisabled . '">';
            $html .= '<a class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-100 gc-page-link" href="#" data-page="' . ($currentPage - 1) . '">' . $lblPrevious . '</a></li>';

            $startPage = max(1, $currentPage - 2);
            $endPage   = min($totalPages, $currentPage + 2);
            if ($startPage > 1) {
                $html .= '<li><a class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-100 gc-page-link" href="#" data-page="1">1</a></li>';
                if ($startPage > 2) {
                    $html .= '<li><span class="px-3 py-1.5 text-sm text-gray-400">&hellip;</span></li>';
                }
            }
            for ($i = $startPage; $i <= $endPage; $i++) {
                $active = $i === $currentPage ? ' bg-blue-600 text-white border-blue-600 hover:bg-blue-700' : ' border border-gray-300 hover:bg-gray-100';
                $html .= '<li><a class="px-3 py-1.5 text-sm rounded gc-page-link' . $active . '" href="#" data-page="' . $i . '">' . $i . '</a></li>';
            }
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    $html .= '<li><span class="px-3 py-1.5 text-sm text-gray-400">&hellip;</span></li>';
                }
                $html .= '<li><a class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-100 gc-page-link" href="#" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
            }
            $nextDisabled = $currentPage >= $totalPages ? ' opacity-50 pointer-events-none' : '';
            $html .= '<li class="' . $nextDisabled . '">';
            $html .= '<a class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-100 gc-page-link" href="#" data-page="' . ($currentPage + 1) . '">' . $lblNext . '</a></li>';
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
        $modalIcon = $isEdit ? 'bi-pencil' : 'bi-plus-lg';

        $html = '<div class="grocery-crud-form-wrapper" id="' . $crudId . '_form">';
        $html .= '<div class="bg-white shadow-sm rounded-lg border border-gray-200">';
        $html .= '<div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">';
        $html .= '<h5 class="text-lg font-semibold text-gray-800"><i class="bi ' . $modalIcon . ' mr-2"></i>' . $lblTitle . '</h5>';
        $html .= '<button type="button" class="text-gray-400 hover:text-gray-600 gc-form-close" aria-label="' . $lblCancel . '">&times;</button>';
        $html .= '</div>';
        $html .= '<div class="p-5">';
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

            $html .= '<div class="mb-4' . ($fieldError ? ' has-error' : '') . '">';
            $html .= '<label for="gc_field_' . $field . '" class="block text-sm font-medium text-gray-700 mb-1">';
            $html .= htmlspecialchars($label);
            if ($isRequired) {
                $html .= ' <span class="text-red-500">*</span>';
            }
            $html .= '</label>';
            $html .= $this->renderFormField($field, $type, $value, $options, $isReadonly, $isUpload, $data);
            if ($fieldError) {
                $html .= '<p class="mt-1 text-sm text-red-600">' . htmlspecialchars($fieldError) . '</p>';
            }
            $html .= '</div>';
        }
        $html .= '<div class="flex gap-2 pt-4 border-t border-gray-200">';
        $html .= '<button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700"><i class="bi bi-check-lg mr-1"></i>' . $lblSave . '</button>';
        $html .= '<button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 gc-form-close"><i class="bi bi-x-lg mr-1"></i>' . $lblCancel . '</button>';
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
                $html .= '<textarea class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '" rows="4"' . $readonlyAttr . '>' . htmlspecialchars((string) $value) . '</textarea>';
                break;
            case 'hidden':
                $html .= '<input type="hidden" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '">';
                break;
            case 'integer':
            case 'numeric':
                $html .= '<input type="number" step="any" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'date':
                $html .= '<input type="date" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'datetime':
                $html .= '<input type="datetime-local" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'time':
                $html .= '<input type="time" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'email':
                $html .= '<input type="email" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'url':
                $html .= '<input type="url" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'phone':
                $html .= '<input type="tel" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'color':
                $html .= '<input type="color" class="w-full h-10 border border-gray-300 rounded px-1 py-1" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
            case 'password':
                $html .= '<input type="password" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '"' . $readonlyAttr . '>';
                if (!empty($value)) {
                    $html .= '<p class="mt-1 text-xs text-gray-500">Leave empty to keep current password.</p>';
                }
                break;
            case 'true_false':
            case 'boolean':
                $checked = !empty($value) ? ' checked' : '';
                $html .= '<label class="inline-flex items-center cursor-pointer">';
                $html .= '<input type="checkbox" class="sr-only peer" id="' . $fieldId . '" name="' . $fieldName . '" value="1"' . $checked . $readonlyAttr . '>';
                $html .= '<div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[""] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>';
                $html .= '</label>';
                break;
            case 'dropdown':
            case 'enum':
            case 'relation':
                $html .= '<select class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '"' . ($isReadonly ? ' disabled' : '') . '>';
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
                $html .= '<div class="border border-gray-300 rounded p-3 max-h-48 overflow-y-auto">';
                if (!empty($options) && is_array($options)) {
                    $selectedValues = is_array($value) ? $value : explode(',', (string) $value);
                    foreach ($options as $optValue => $optLabel) {
                        $checked = in_array((string) $optValue, $selectedValues, true) ? ' checked' : '';
                        $optId = $fieldId . '_' . md5((string) $optValue);
                        $html .= '<label class="flex items-center gap-2 mb-1 text-sm" for="' . $optId . '">';
                        $html .= '<input type="checkbox" id="' . $optId . '" name="' . $fieldName . '[]" value="' . htmlspecialchars((string) $optValue) . '"' . $checked . ($isReadonly ? ' disabled' : '') . ' class="rounded border-gray-300">';
                        $html .= '<span>' . htmlspecialchars((string) $optLabel) . '</span>';
                        $html .= '</label>';
                    }
                }
                $html .= '</div>';
                break;
            case 'image':
            case 'file':
                $html .= '<input type="file" class="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" id="' . $fieldId . '" name="' . $fieldName . '"' . $readonlyAttr . '>';
                if (!empty($value)) {
                    $html .= '<div class="mt-2">';
                    if ($type === 'image') {
                        $html .= '<img src="' . htmlspecialchars((string) $value) . '" class="max-h-24 rounded border" alt="">';
                    } else {
                        $html .= '<a href="' . htmlspecialchars((string) $value) . '" target="_blank" class="inline-flex items-center px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50">';
                        $html .= '<i class="bi bi-paperclip mr-1"></i> ' . basename((string) $value) . '</a>';
                    }
                    $html .= '</div>';
                    $html .= '<input type="hidden" name="' . $fieldName . '_existing" value="' . htmlspecialchars(basename((string) $value)) . '">';
                }
                break;
            case 'read_only':
                $html .= '<input type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-gray-50" id="' . $fieldId . '" value="' . htmlspecialchars((string) $value) . '" readonly disabled>';
                break;
            case 'repeater':
                if ($rDef === null) break;
                $repeatables = $rDef['repeatables'] ?? [];
                $html .= '<div class="gc-repeater-container border border-gray-300 rounded p-4">';
                foreach ($rValues as $rIndex => $rItem) {
                    $html .= '<div class="gc-repeater-item border border-gray-200 rounded p-4 mb-3 bg-white">';
                    $html .= '<div class="flex justify-end mb-2">';
                    $html .= '<button type="button" class="px-2 py-1 text-xs text-red-600 bg-red-50 rounded hover:bg-red-100 gc-repeater-remove"><i class="bi bi-trash"></i></button>';
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
                        $html .= '<label for="' . $inputId . '" class="block text-xs font-medium text-gray-600 mb-1">' . htmlspecialchars($sfLabel) . '</label>';
                        $html .= $this->renderRepeaterSubField($inputName, $inputId, $sfType, $sfValue, $sfOpts);
                        $html .= '</div>';
                    }
                    $html .= '</div>';
                }
                $template = '<div class="gc-repeater-item border border-gray-200 rounded p-4 mb-3 bg-white">';
                $template .= '<div class="flex justify-end mb-2">';
                $template .= '<button type="button" class="px-2 py-1 text-xs text-red-600 bg-red-50 rounded hover:bg-red-100 gc-repeater-remove"><i class="bi bi-trash"></i></button>';
                $template .= '</div>';
                foreach ($repeatables as $subField) {
                    $sfName  = $subField['name'];
                    $sfLabel = $subField['label'] ?? ucfirst($sfName);
                    $sfType  = $subField['type'] ?? 'text';
                    $sfOpts  = $subField['options'] ?? [];
                    $inputName = $fieldName . '[__INDEX__][' . $sfName . ']';
                    $inputId   = $fieldId . '__INDEX__' . $sfName;
                    $template .= '<div class="mb-2">';
                    $template .= '<label for="' . $inputId . '" class="block text-xs font-medium text-gray-600 mb-1">' . htmlspecialchars($sfLabel) . '</label>';
                    $template .= $this->renderRepeaterSubField($inputName, $inputId, $sfType, '', $sfOpts, true);
                    $template .= '</div>';
                }
                $template .= '</div>';
                $html .= '<div class="gc-repeater-template" style="display:none">' . $template . '</div>';
                $html .= '<button type="button" class="px-3 py-1.5 text-sm text-blue-600 border border-blue-300 rounded hover:bg-blue-50 mt-2 gc-repeater-add"><i class="bi bi-plus-lg"></i> Add Item</button>';
                $html .= '</div>';
                break;
            default:
                $html .= '<input type="text" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $fieldId . '" name="' . $fieldName . '" value="' . htmlspecialchars((string) $value) . '"' . $readonlyAttr . '>';
                break;
        }
        return $html;
    }

    private function renderRepeaterSubField(string $name, string $id, string $type, mixed $value, array $options = [], bool $disabled = false): string
    {
        $d = $disabled ? ' disabled' : '';
        switch ($type) {
            case 'textarea':
                return '<textarea class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $id . '" name="' . $name . '" rows="2"' . $d . '>' . htmlspecialchars((string) $value) . '</textarea>';
            case 'integer':
            case 'numeric':
                return '<input type="number" step="any" class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';
            case 'select':
            case 'dropdown':
                $html = '<select class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $id . '" name="' . $name . '"' . $d . '>';
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
                $html = '<label class="inline-flex items-center cursor-pointer">';
                $html .= '<input type="checkbox" class="sr-only peer" id="' . $id . '" name="' . $name . '" value="1"' . $checked . $d . '>';
                $html .= '<div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[""] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>';
                $html .= '</label>';
                return $html;
            case 'hidden':
                return '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';
            default:
                return '<input type="text" class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="' . $id . '" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '"' . $d . '>';
        }
    }

    public function renderSubGrid(array $config, array $records): string
    {
        $columns      = $config['columns'] ?? [];
        $columnLabels = $config['columnLabels'] ?? [];
        $relatedTable = $config['relatedTable'] ?? '';
        $recordCount  = count($records);

        $html = '<div class="gc-subgrid-inner bg-white rounded border border-gray-200">';
        $tableLabel = ucfirst(str_replace('_', ' ', $relatedTable));
        $html .= '<div class="flex items-center justify-between px-4 py-2 bg-gray-100 border-b border-gray-200">';
        $html .= '<span class="text-sm font-semibold text-gray-700"><i class="bi bi-grid-3x3-gap-fill mr-2"></i>' . htmlspecialchars($tableLabel) . '</span>';
        $html .= '<span class="text-xs font-medium px-2 py-1 bg-blue-100 text-blue-700 rounded-full">' . $recordCount . ' data</span>';
        $html .= '</div>';
        $html .= '<div class="overflow-x-auto">';
        $html .= '<table class="w-full border-collapse gc-subgrid-table">';
        $html .= '<thead><tr class="bg-gray-50 border-b border-gray-200">';
        foreach ($columns as $col) {
            $label = $columnLabels[$col] ?? ucfirst(str_replace('_', ' ', $col));
            $html .= '<th class="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">' . htmlspecialchars($label) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if (empty($records)) {
            $colspan = count($columns);
            $html .= '<tr><td colspan="' . $colspan . '" class="text-center text-gray-500 py-6 text-sm">Tidak ada data terkait.</td></tr>';
        } else {
            foreach ($records as $row) {
                $html .= '<tr class="border-b border-gray-100 hover:bg-blue-50 transition-colors">';
                foreach ($columns as $col) {
                    $value = $row[$col] ?? '';
                    $html .= '<td class="px-3 py-2 text-sm text-gray-700">' . htmlspecialchars((string) $value) . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table></div></div>';
        return $html;
    }
}
