/**
 * Grocery CRUD - JavaScript functionality
 * Handles AJAX CRUD operations, form submissions, and UI interactions
 */
(function ($) {
    'use strict';

    // ======== Alert Handler ========
    function showAlert(message, type) {
        type = type || 'success';
        var icon = type === 'success' ? 'bi-check-circle-fill'
                 : type === 'danger' ? 'bi-exclamation-triangle-fill'
                 : type === 'warning' ? 'bi-exclamation-circle-fill'
                 : 'bi-info-circle-fill';

        var alertHtml = '<div class="gc-alert alert alert-' + type + ' alert-dismissible fade show shadow" role="alert">'
            + '<i class="bi ' + icon + ' me-2"></i>' + message
            + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
            + '</div>';

        var $alert = $(alertHtml);
        $('body').append($alert);

        setTimeout(function () {
            $alert.alert('close');
        }, 4000);
    }

    // ======== Loading Overlay ========
    function showLoading() {
        if ($('.gc-loading').length === 0) {
            $('body').append('<div class="gc-loading"></div>');
        }
    }

    function hideLoading() {
        $('.gc-loading').remove();
    }

    // ======== Modal Manager ========
    var GcModal = {
        show: function (html) {
            this.remove();
            var modalHtml = '<div class="modal fade gc-modal" tabindex="-1" role="dialog">'
                + '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">'
                + '<div class="modal-content">'
                + '<div class="modal-body p-0">'
                + html
                + '</div>'
                + '</div>'
                + '</div>'
                + '</div>';

            var $modal = $(modalHtml);
            $('body').append($modal);
            $modal.modal('show');

            return $modal;
        },
        hide: function () {
            $('.gc-modal').modal('hide');
        },
        remove: function () {
            $('.gc-modal').remove();
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
        }
    };

    // ======== Form Serializer (handles checkboxes) ========
    function serializeForm($form) {
        var data = {};
        var formArray = $form.serializeArray();

        // Handle checkboxes
        $form.find('input[type="checkbox"]').each(function () {
            var $cb = $(this);
            if ($cb.prop('checked')) {
                if (data[$cb.attr('name')] === undefined) {
                    data[$cb.attr('name')] = [];
                }
                data[$cb.attr('name')].push($cb.val());
            }
        });

        // Overwrite with serializeArray values (for non-checkbox inputs)
        $.each(formArray, function (i, field) {
            if (field.name.indexOf('[]') === -1) {
                data[field.name] = field.value;
            } else {
                var name = field.name.replace('[]', '');
                if (data[name] === undefined) {
                    data[name] = [];
                }
                // Only add if not already added by checkbox handler
                if ($.inArray(field.value, data[name]) === -1) {
                    data[name].push(field.value);
                }
            }
        });

        return data;
    }

    // ======== File Upload FormData ========
    function buildFormData($form) {
        var formData = new FormData($form[0]);
        var mode = $form.data('mode');

        // Add the CRUD action (missing from FormData)
        formData.append('gc_action', mode);

        // Handle checkboxes not in FormData automatically
        $form.find('input[type="checkbox"]:not(:checked)').each(function () {
            var $cb = $(this);
            var name = $cb.attr('name');
            // Skip array fields (e.g., tags[]) — they're only meaningful when checked.
            // Setting '0' on an array field makes PHP see ['0'], which causes FK errors.
            if (name.indexOf('[]') !== -1) {
                return;
            }
            // FormData doesn't include unchecked checkboxes
            // We need to ensure the field is present
            if (!formData.has(name)) {
                formData.set(name, '0');
            }
        });

        return formData;
    }

    // ======== CRUD Operations ========
    function refreshList($wrapper) {
        var crudId = $wrapper.attr('id');
        var $parent = $wrapper.parent();
        var page = $wrapper.data('currentPage') || 1;
        var $searchInput = $wrapper.find('.gc-search-input');
        var search = $searchInput.val() || '';
        var advancedFilters = $wrapper.data('gcAdvancedFilters') || [];
        var sortField = $wrapper.data('sortField') || null;
        var sortDir = $wrapper.data('sortDir') || null;
        var wasFocused = document.activeElement === $searchInput[0];
        var caretPos = wasFocused && $searchInput[0].selectionStart !== undefined
            ? $searchInput[0].selectionStart
            : -1;

        // Track filter input focus for restore after DOM replacement
        var $focusedFilter = $wrapper.find('.gc-column-filter:focus');
        var wasFilterFocused = $focusedFilter.length > 0;
        var focusedFilterField = wasFilterFocused ? $focusedFilter.data('filter-field') : null;
        var filterCaretPos = wasFilterFocused && $focusedFilter[0].selectionStart !== undefined
            ? $focusedFilter[0].selectionStart
            : -1;

        // Collect column filters from DOM
        var filters = {};
        $wrapper.find('.gc-column-filter').each(function () {
            var $el = $(this);
            var field = $el.data('filter-field');
            var val = $el.val();
            if (val) {
                filters[field] = val;
            }
        });

        showLoading();

        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: {
                gc_action: 'list',
                page: page,
                search: search,
                sort_field: sortField,
                sort_dir: sortDir,
                filters: Object.keys(filters).length > 0 ? JSON.stringify(filters) : undefined,
                advanced_filters: JSON.stringify(advancedFilters)
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $wrapper.replaceWith(response.html);
                    // Re-bind events
                    bindEvents();
                    // Restore search value (crudId changes on re-render via uniqid)
                    var $newSearchInput = $parent.find('.grocery-crud-wrapper .gc-search-input');
                    $newSearchInput.val(search);
                    // Restore advanced filters
                    var savedFilters = $wrapper.data('gcAdvancedFilters');
                    if (savedFilters && savedFilters.length) {
                        $wrapper.data('gcAdvancedFilters', savedFilters);
                    }
                    // Restore focus and caret position if previously focused
                    if (wasFocused) {
                        $newSearchInput.focus();
                        if (caretPos >= 0 && $newSearchInput[0].setSelectionRange) {
                            $newSearchInput[0].setSelectionRange(caretPos, caretPos);
                        }
                    }
                    // Sync clear button visibility (fresh HTML always has it hidden)
                    var $clearBtn = $newSearchInput.closest('.input-group').find('.gc-search-clear');
                    if (search) {
                        $clearBtn.show();
                    } else {
                        $clearBtn.hide();
                    }
                    // Restore focus to filter input if previously focused
                    if (wasFilterFocused && focusedFilterField) {
                        var $newFilter = $parent.find('.grocery-crud-wrapper .gc-column-filter[data-filter-field="' + focusedFilterField + '"]');
                        if ($newFilter.length) {
                            $newFilter.focus();
                            if (filterCaretPos >= 0 && $newFilter[0].setSelectionRange) {
                                $newFilter[0].setSelectionRange(filterCaretPos, filterCaretPos);
                            }
                        }
                    }
                    // Populate columns menu and filter selects from table headers
                    var $newWrapper = $parent.find('.grocery-crud-wrapper');
                    populateColumnsAndFilters($newWrapper);

                    // Restore advanced filter panel items and visibility
                    if (advancedFilters && advancedFilters.length) {
                        var $panel = $newWrapper.find('.gc-filter-panel');
                        var $rows = $panel.find('.gc-filter-rows');
                        var $template = $rows.find('.gc-filter-item-template');

                        advancedFilters.forEach(function (f) {
                            var $item = $template.clone()
                                .removeClass('gc-filter-item-template')
                                .addClass('gc-filter-item')
                                .show();
                            $item.find('.gc-filter-col').val(f.field);
                            $item.find('.gc-filter-op').val(f.operator);
                            $item.find('.gc-filter-val').val(f.value);
                            $rows.append($item);
                        });

                        $panel.show();
                    }
                } else {
                    showAlert(response.message || 'Failed to load data.', 'danger');
                }
            },
            error: function () {
                showAlert('An error occurred while loading data.', 'danger');
            },
            complete: function () {
                hideLoading();
            }
        });
    }

    function loadAddForm($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');

        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: { gc_action: 'add_form' },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    var $modal = GcModal.show(response.html);
                    bindFormEvents($modal);
                } else {
                    showAlert(response.message || 'Failed to load form.', 'danger');
                }
            },
            error: function () {
                showAlert('An error occurred.', 'danger');
            },
            complete: function () {
                hideLoading();
            }
        });
    }

    function loadEditForm($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        var id = $btn.data('id');

        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: { gc_action: 'edit_form', id: id },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    var $modal = GcModal.show(response.html);
                    bindFormEvents($modal);
                } else {
                    showAlert(response.message || 'Failed to load form.', 'danger');
                }
            },
            error: function () {
                showAlert('An error occurred.', 'danger');
            },
            complete: function () {
                hideLoading();
            }
        });
    }

    // ======== Columns & Filter Populate ========
    function populateColumnsAndFilters($wrapper) {
        // Columns menu: populate checkboxes from table headers
        var $menu = $wrapper.find('.gc-columns-menu');
        if ($menu.length) {
            $menu.empty();
            $wrapper.find('.gc-table th[data-column]').each(function () {
                var col = $(this).data('column');
                var label = $(this).data('label') || col;
                var isHidden = $(this).hasClass('d-none');
                var $cb = $('<div class="form-check">'
                    + '<input type="checkbox" class="form-check-input" id="col_'
                    + col + '" data-column="' + col + '"' + (isHidden ? '' : ' checked') + '>'
                    + '<label class="form-check-label" for="col_' + col + '">'
                    + $('<span>').text(label).html()
                    + '</label></div>');
                $menu.append($cb);
            });
        }

        // Filter column selects: populate options from table headers
        $wrapper.find('.gc-filter-col').each(function () {
            var $select = $(this);
            var currentVal = $select.val();
            // Only populate if empty (template only has the placeholder option)
            if ($select.find('option[value]').length <= 1) {
                $wrapper.find('.gc-table th[data-column]').each(function () {
                    var col = $(this).data('column');
                    var label = $(this).data('label') || col;
                    $select.append('<option value="' + col + '">' + $('<span>').text(label).html() + '</option>');
                });
                if (currentVal) $select.val(currentVal);
            }
        });
    }

    function submitForm($form) {
        var $modal = $form.closest('.modal');
        var $submitBtn = $form.find('button[type="submit"]');
        var mode = $form.data('mode');
        var hasFile = $form.find('input[type="file"]').length > 0;

        // Disable button
        $submitBtn.prop('disabled', true).addClass('btn-gc-loading');

        var ajaxConfig = {
            url: window.location.href,
            method: 'POST',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    GcModal.hide();
                    showAlert(response.message, 'success');
                    // Refresh the list
                    refreshList($('.grocery-crud-wrapper'));
                } else {
                    // Show validation errors
                    if (response.errors) {
                        // Clear previous errors
                        $form.find('.has-error').removeClass('has-error');
                        $form.find('.invalid-feedback').remove();

                        $.each(response.errors, function (field, message) {
                            var $field = $form.find('[name="' + field + '"], [name="' + field + '[]"]').first();
                            var $group = $field.closest('.mb-3');
                            $group.addClass('has-error');
                            $group.append('<div class="invalid-feedback d-block">' + message + '</div>');
                        });
                    }
                    showAlert(response.message || 'Operation failed.', 'danger');
                }
            },
            error: function () {
                showAlert('An error occurred.', 'danger');
            },
            complete: function () {
                $submitBtn.prop('disabled', false).removeClass('btn-gc-loading');
            }
        };

        if (hasFile) {
            ajaxConfig.data = buildFormData($form);
            ajaxConfig.processData = false;
            ajaxConfig.contentType = false;
        } else {
            ajaxConfig.data = $form.serialize() + '&gc_action=' + mode;
        }

        $.ajax(ajaxConfig);
    }

    function restoreRecord($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        var id = $btn.data('id');

        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { gc_action: 'restore', id: id },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    refreshList($wrapper);
                } else {
                    showAlert(response.message || 'Failed to restore record.', 'danger');
                }
            },
            error: function () {
                showAlert('An error occurred.', 'danger');
            },
            complete: function () {
                hideLoading();
            }
        });
    }

    function loadTrashList($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');

        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: { gc_action: 'trash_list' },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $wrapper.replaceWith(response.html);
                    bindEvents();
                } else {
                    showAlert(response.message || 'Failed to load trash.', 'danger');
                }
            },
            error: function () {
                showAlert('An error occurred.', 'danger');
            },
            complete: function () {
                hideLoading();
            }
        });
    }

    function deleteRecord($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        var id = $btn.data('id');
        var message = $wrapper.data('confirm-delete') || 'Are you sure you want to delete this record?';

        if (!confirm(message)) {
            return;
        }

        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { gc_action: 'delete', id: id },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    refreshList($wrapper);
                } else {
                    showAlert(response.message || 'Failed to delete record.', 'danger');
                }
            },
            error: function () {
                showAlert('An error occurred.', 'danger');
            },
            complete: function () {
                hideLoading();
            }
        });
    }

    function loadSubGrid($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        var $row = $btn.closest('tr');
        var $subRow = $row.next('.gc-subgrid-row');
        var subGridField = $btn.data('subgrid');
        var parentId = $btn.data('parent-id');

        if ($subRow.length === 0) return;

        // If already loaded, just toggle
        var $content = $subRow.find('.gc-subgrid-content');
        var $table = $content.find('.gc-subgrid-inner');
        if ($table.length > 0) {
            $subRow.toggle();
            $btn.find('i').toggleClass('bi-chevron-right bi-chevron-down');
            return;
        }

        // Load via AJAX
        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: {
                gc_action: 'sub_grid',
                sub_grid: subGridField,
                parent_id: parentId
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $content.html(response.html);
                    $subRow.show();
                    $btn.find('i').removeClass('bi-chevron-right').addClass('bi-chevron-down');
                } else {
                    showAlert(response.message || 'Failed to load sub-grid.', 'danger');
                }
            },
            error: function () {
                showAlert('An error occurred loading sub-grid.', 'danger');
            },
            complete: function () {
                hideLoading();
            }
        });
    }

    function handleExport($btn, format) {
        showLoading();
        window.location.href = window.location.pathname
            + '?gc_action=export&format=' + format;
        setTimeout(function () {
            hideLoading();
        }, 2000);
    }

    // ======== Event Binding ========
    function bindEvents() {
        // Add button
        $(document).off('click', '.btn-gc-add').on('click', '.btn-gc-add', function (e) {
            e.preventDefault();
            loadAddForm($(this));
        });

        // Edit button
        $(document).off('click', '.btn-gc-edit').on('click', '.btn-gc-edit', function (e) {
            e.preventDefault();
            loadEditForm($(this));
        });

        // Delete button
        $(document).off('click', '.btn-gc-delete').on('click', '.btn-gc-delete', function (e) {
            e.preventDefault();
            deleteRecord($(this));
        });

        // Restore button (trashed view)
        $(document).off('click', '.btn-gc-restore').on('click', '.btn-gc-restore', function (e) {
            e.preventDefault();
            restoreRecord($(this));
        });

        // Trash list button
        $(document).off('click', '.gc-btn-trash').on('click', '.gc-btn-trash', function (e) {
            e.preventDefault();
            loadTrashList($(this));
        });

        // Active list button (from trashed view)
        $(document).off('click', '.gc-btn-active').on('click', '.gc-btn-active', function (e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        });

        // Sub-grid expand/collapse toggle
        $(document).off('click', '.gc-subgrid-toggle').on('click', '.gc-subgrid-toggle', function (e) {
            e.preventDefault();
            loadSubGrid($(this));
        });

        // Pagination links
        $(document).off('click', '.gc-page-link').on('click', '.gc-page-link', function (e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var page = $(this).data('page');
            $wrapper.data('currentPage', page);
            refreshList($wrapper);
        });

        // Search - realtime on keyup (debounced)
        $(document).off('keyup', '.gc-search-input').on('keyup', '.gc-search-input', $.debounce(function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        }, 400));

        // Search - immediate on Enter key
        $(document).off('keydown', '.gc-search-input').on('keydown', '.gc-search-input', function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                var $wrapper = $(this).closest('.grocery-crud-wrapper');
                $wrapper.data('currentPage', 1);
                refreshList($wrapper);
            }
        });

        // Show/hide clear search button on input change
        $(document).off('input', '.gc-search-input').on('input', '.gc-search-input', function () {
            var $clearBtn = $(this).closest('.input-group').find('.gc-search-clear');
            if ($(this).val()) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }
        });

        // Clear search button
        $(document).off('click', '.gc-search-clear').on('click', '.gc-search-clear', function (e) {
            e.preventDefault();
            var $input = $(this).closest('.input-group').find('.gc-search-input');
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $input.val('');
            $(this).hide();
            $input.focus();
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        });

        // ======== Column Filters ========
        var filterTimer = null;
        $(document).off('change', '.gc-column-filter').on('change', '.gc-column-filter', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        });
        $(document).off('input', '.gc-column-filter').on('input', '.gc-column-filter', function () {
            var $self = $(this);
            // Only debounce text inputs (selects use 'change' above)
            if ($self.is('select')) return;
            clearTimeout(filterTimer);
            filterTimer = setTimeout(function () {
                var $wrapper = $self.closest('.grocery-crud-wrapper');
                $wrapper.data('currentPage', 1);
                refreshList($wrapper);
            }, 400);
        });

        // ======== Batch Actions ========
        // Select-all checkbox
        $(document).off('change', '.gc-select-all').on('change', '.gc-select-all', function () {
            var isChecked = $(this).prop('checked');
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $wrapper.find('.gc-row-checkbox').prop('checked', isChecked);
            updateBatchToolbar($wrapper);
        });

        // Row checkbox
        $(document).off('change', '.gc-row-checkbox').on('change', '.gc-row-checkbox', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var allChecked = $wrapper.find('.gc-row-checkbox').length === $wrapper.find('.gc-row-checkbox:checked').length;
            $wrapper.find('.gc-select-all').prop('checked', allChecked);
            updateBatchToolbar($wrapper);
        });

        // Batch action button
        $(document).off('click', '.gc-batch-action').on('click', '.gc-batch-action', function (e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var actionId = $(this).data('batch-action');
            var selectedIds = [];
            $wrapper.find('.gc-row-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
            });
            if (selectedIds.length === 0) return;

            // Detect if we're in trash view (the "Active Records" button only appears there)
            var isTrashView = $wrapper.find('.gc-btn-active').length > 0;

            if (actionId === 'delete_selected') {
                var msg = isTrashView
                    ? 'Are you sure you want to permanently delete ' + selectedIds.length + ' selected record(s)? This cannot be undone.'
                    : 'Are you sure you want to delete ' + selectedIds.length + ' selected record(s)?';
                if (!confirm(msg)) {
                    return;
                }
            }

            showLoading();

            var requestData = {
                gc_action: 'batch_action',
                batch_action: actionId,
                ids: selectedIds
            };

            // When in trash view, delete_selected should permanently delete
            if (isTrashView && actionId === 'delete_selected') {
                requestData.permanent_delete = 1;
            }

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: requestData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showAlert(response.message || 'Action completed.', 'success');
                        refreshList($wrapper);
                    } else {
                        showAlert(response.message || 'Action failed.', 'danger');
                        hideLoading();
                    }
                },
                error: function () {
                    showAlert('An error occurred.', 'danger');
                    hideLoading();
                }
            });
        });

        // Batch toolbar helper
        function updateBatchToolbar($wrapper) {
            var $toolbar = $wrapper.find('.gc-batch-toolbar');
            var $num = $toolbar.find('.gc-selected-num');
            var count = $wrapper.find('.gc-row-checkbox:checked').length;
            if (count > 0) {
                $toolbar.show();
                $num.text(count);
            } else {
                $toolbar.hide();
            }
        }

        // Sortable column headers
        $(document).off('click', '.gc-sortable').on('click', '.gc-sortable', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var field = $(this).data('sort-field');
            var dir = $(this).data('sort-dir');
            $wrapper.data('sortField', field);
            $wrapper.data('sortDir', dir);
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        });

        // Export
        $(document).off('click', '[data-export]').on('click', '[data-export]', function (e) {
            e.preventDefault();
            handleExport($(this), $(this).data('export'));
        });

        // ======== Columns Dropdown Toggle ========
        $(document).off('change', '.gc-columns-menu input[type="checkbox"]').on('change', '.gc-columns-menu input[type="checkbox"]', function () {
            var col = $(this).data('column');
            var $table = $(this).closest('.grocery-crud-wrapper').find('.gc-table');
            if ($(this).is(':checked')) {
                $table.find('th[data-column="' + col + '"], td[data-column="' + col + '"]').removeClass('d-none');
            } else {
                $table.find('th[data-column="' + col + '"], td[data-column="' + col + '"]').addClass('d-none');
            }
        });

        // ======== Filter Panel ========
        // Toggle filter panel
        $(document).off('click', '.gc-filter-btn').on('click', '.gc-filter-btn', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var $panel = $wrapper.find('.gc-filter-panel');
            $panel.toggle();
        });

        // Add filter row
        $(document).off('click', '.gc-filter-add').on('click', '.gc-filter-add', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var $rows = $wrapper.find('.gc-filter-rows');
            var $template = $rows.find('.gc-filter-item-template').clone().removeClass('gc-filter-item-template').addClass('gc-filter-item').show();
            $template.find('input').val('');
            $template.find('select').prop('selectedIndex', 0);
            $rows.append($template);
        });

        // Remove filter row
        $(document).off('click', '.gc-filter-item-remove').on('click', '.gc-filter-item-remove', function () {
            $(this).closest('.gc-filter-item').remove();
        });

        // Apply filters
        $(document).off('click', '.gc-filter-apply').on('click', '.gc-filter-apply', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var filters = [];
            $wrapper.find('.gc-filter-item').each(function () {
                var col = $(this).find('.gc-filter-col').val();
                var op = $(this).find('.gc-filter-op').val();
                var val = $(this).find('.gc-filter-val').val();
                if (col && val) {
                    filters.push({field: col, operator: op, value: val});
                }
            });
            $wrapper.data('gcAdvancedFilters', filters);
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        });

        // Clear filters
        $(document).off('click', '.gc-filter-clear').on('click', '.gc-filter-clear', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $wrapper.find('.gc-filter-item').remove();
            $wrapper.find('.gc-filter-panel').hide();
            $wrapper.removeData('gcAdvancedFilters');
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        });

        // ======== Settings Save/Load/Reset ========
        // Save settings
        $(document).off('click', '.gc-settings-save').on('click', '.gc-settings-save', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var url = window.location.href;
            var settings = {
                columns: {},
                filters: $wrapper.data('gcAdvancedFilters') || []
            };
            $wrapper.find('.gc-columns-menu input[type="checkbox"]').each(function () {
                settings.columns[$(this).data('column')] = $(this).is(':checked');
            });
            try {
                localStorage.setItem('gc_settings_' + btoa(url), JSON.stringify(settings));
                showAlert('Settings saved.', 'success');
            } catch (e) {
                showAlert('Could not save settings.', 'danger');
            }
        });

        // Load settings
        $(document).off('click', '.gc-settings-load').on('click', '.gc-settings-load', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var url = window.location.href;
            try {
                var raw = localStorage.getItem('gc_settings_' + btoa(url));
                if (!raw) { showAlert('No saved settings found.', 'warning'); return; }
                var settings = JSON.parse(raw);
                // Restore columns
                if (settings.columns) {
                    $wrapper.find('.gc-columns-menu input[type="checkbox"]').each(function () {
                        var col = $(this).data('column');
                        if (settings.columns[col] !== undefined) {
                            $(this).prop('checked', settings.columns[col]).trigger('change');
                        }
                    });
                }
                // Restore filters
                if (settings.filters && settings.filters.length) {
                    $wrapper.data('gcAdvancedFilters', settings.filters);
                    refreshList($wrapper);
                }
                showAlert('Settings loaded.', 'success');
            } catch (e) {
                showAlert('Could not load settings.', 'danger');
            }
        });

        // Reset settings
        $(document).off('click', '.gc-settings-reset').on('click', '.gc-settings-reset', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var url = window.location.href;
            try {
                localStorage.removeItem('gc_settings_' + btoa(url));
                $wrapper.find('.gc-columns-menu input[type="checkbox"]').each(function () {
                    $(this).prop('checked', true).trigger('change');
                });
                $wrapper.removeData('gcAdvancedFilters');
                $wrapper.find('.gc-filter-item').remove();
                $wrapper.find('.gc-filter-panel').hide();
                showAlert('Settings reset to defaults.', 'success');
            } catch (e) {}
        });

        // Image viewer - click thumbnail to show enlarged
        $(document).off('click', '.gc-table img.gc-thumb').on('click', '.gc-table img.gc-thumb', function () {
            var $img = $(this);
            var src = $img.attr('src') || $img.data('src');
            if (!src) return;

            var modalHtml = '<div class="modal fade gc-image-viewer" tabindex="-1">'
                + '<div class="modal-dialog modal-dialog-centered">'
                + '<div class="modal-content">'
                + '<div class="modal-header border-0 pb-0">'
                + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                + '</div>'
                + '<div class="modal-body">'
                + '<img src="' + src + '" alt="" style="display:none">'
                + '</div>'
                + '</div>'
                + '</div>'
                + '</div>';

            var $modal = $(modalHtml);
            var $modalImg = $modal.find('img');
            $('body').append($modal);
            $modal.modal('show');

            // Adjust dialog to image natural size after load
            $modalImg.on('load', function () {
                var $dialog = $modal.find('.modal-dialog');
                var imgW = this.naturalWidth;
                var imgH = this.naturalHeight;

                // Cap at viewport - some margin
                var maxW = window.innerWidth * 0.9;
                var maxH = window.innerHeight * 0.85;

                if (imgW > maxW || imgH > maxH) {
                    // Image bigger than viewport — let CSS handle it
                    $modalImg.show();
                } else {
                    // Image fits — size dialog to image
                    $modalImg.css({display:'block', width: imgW + 'px', height: 'auto'});
                    $dialog.css('max-width', (imgW + 40) + 'px');
                }

                // Re-center modal after content resize
                $modal[0]._isShown && $modal.modal('handleUpdate');
            });

            // If already cached, trigger load manually
            if ($modalImg[0].complete) {
                $modalImg.trigger('load');
            } else {
                // Ensure img is shown even if load fails
                $modalImg.on('error', function () {
                    $modalImg.show();
                });
            }

            $modal.on('hidden.bs.modal', function () {
                $modal.remove();
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
            });
        });

        // ======== Repeater Fields ========
        // Add item
        $(document).off('click', '.gc-repeater-add').on('click', '.gc-repeater-add', function () {
            var $btn = $(this);
            var $container = $btn.closest('.gc-repeater-container');
            var $template = $container.find('.gc-repeater-template');
            var index = $container.find('.gc-repeater-item').not($container.find('.gc-repeater-template .gc-repeater-item')).length;

            var html = $template.html().replace(/__INDEX__/g, index);
            html = html.replace(/ disabled/g, '');  // Remove disabled from cloned items
            $btn.before(html);
        });

        // Remove item
        $(document).off('click', '.gc-repeater-remove').on('click', '.gc-repeater-remove', function () {
            $(this).closest('.gc-repeater-item').remove();
        });

        // Populate columns menu and filter selects for existing wrappers
        $(document).find('.grocery-crud-wrapper').each(function () {
            populateColumnsAndFilters($(this));
        });
    }

    function bindFormEvents($modal) {
        // Form submission
        $modal.on('submit', '.gc-form', function (e) {
            e.preventDefault();
            submitForm($(this));
        });

        // Close button
        $modal.on('click', '.gc-form-close', function (e) {
            e.preventDefault();
            GcModal.hide();
        });

        // Close on backdrop click
        $modal.on('hidden.bs.modal', function () {
            GcModal.remove();
        });
    }

    // ======== Debounce helper ========
    $.debounce = function (fn, delay) {
        var timer = null;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    };

    // ======== Init ========
    // ======== Bootstrap polyfill for non-Bootstrap themes ========
    function bootstrapPolyfill() {
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Dropdown === 'function') {
            return; // Bootstrap is loaded, no polyfill needed
        }

        // Dropdown polyfill
            $(document).on('click.dropdown', '[data-bs-toggle="dropdown"]', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var $wrapper = $btn.closest('.dropdown, .relative');
                var $menu = $wrapper.find('.dropdown-menu, .dropdown-content, .gc-columns-menu, .gc-settings-menu');
                if ($menu.length === 0) {
                    $menu = $btn.next('.dropdown-menu, .dropdown-content, ul');
                }

                // Close all other dropdowns
                $('[data-bs-toggle="dropdown"]').not($btn).each(function () {
                    var $otherWrapper = $(this).closest('.dropdown, .relative');
                    var $otherMenu = $otherWrapper.find('.dropdown-menu, .dropdown-content, .gc-columns-menu, .gc-settings-menu');
                    if ($otherMenu.length === 0) {
                        $otherMenu = $(this).next('.dropdown-menu, .dropdown-content, ul');
                    }
                    $otherMenu.hide();
                });

                $menu.toggle();
            });

            // Close dropdowns on outside click
            $(document).on('click.dropdown', function (e) {
                if (!$(e.target).closest('[data-bs-toggle="dropdown"]').length
                    && !$(e.target).closest('.dropdown-menu, .dropdown-content').length) {
                    $('.dropdown-menu, .dropdown-content, .gc-columns-menu, .gc-settings-menu').hide();
                }
            });

        // Modal polyfill for non-Bootstrap themes
        // Note: Always override because Materialize etc. also define $.fn.modal
        // and we need Bootstrap-compatible modal behavior for GcModal
        $.fn.modal = function (action) {
                if (action === 'show') {
                    return this.each(function () {
                        $(this).addClass('show').css('display', 'block');
                        $('body').addClass('modal-open');
                        // Backdrop
                        if ($('.modal-backdrop').length === 0) {
                            $('body').append('<div class="modal-backdrop" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:1050;background:rgba(0,0,0,0.5)"></div>');
                        }
                    });
                }
                if (action === 'hide') {
                    return this.each(function () {
                        $(this).removeClass('show').css('display', 'none');
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                    });
                }
            };

        // Alert polyfill
        if (typeof $.fn.alert !== 'function') {
            $.fn.alert = function () {
                return this.each(function () {
                    var $alert = $(this);
                    setTimeout(function () {
                        $alert.fadeOut(function () {
                            $alert.remove();
                        });
                    }, 4000);
                });
            };
            $(document).on('click', '.gc-alert .btn-close', function () {
                $(this).closest('.gc-alert').remove();
            });
        }
    }

    $(document).ready(function () {
        bootstrapPolyfill();
        bindEvents();

        // Store confirmation messages
        $('.grocery-crud-wrapper').each(function () {
            var $wrapper = $(this);
            var deleteMsg = $wrapper.find('[data-confirm-delete]').data('confirm-delete');
            if (deleteMsg) {
                $wrapper.data('confirm-delete', deleteMsg);
            }
        });
    });

})(jQuery);
