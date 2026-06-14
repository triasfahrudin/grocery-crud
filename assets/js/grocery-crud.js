/**
 * Grocery CRUD - Fungsionalitas JavaScript
 * Menangani operasi CRUD AJAX, pengiriman formulir, dan interaksi UI
 */
(function ($) {
    'use strict';

    // ======== Penangan Alert ========

    /** @var {string|null} ID catatan yang terkunci untuk pengeditan (untuk dilepaskan saat ditutup) */
    var _lockedRecordId = null;

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

    // ======== Overlay Loading ========
    function showLoading() {
        if ($('.gc-loading').length === 0) {
            $('body').append('<div class="gc-loading"></div>');
        }
    }

    function hideLoading() {
        $('.gc-loading').remove();
    }

    // ======== Manajer Modal ========
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

            // Inisialisasi elemen formulir Materialize jika Materialize dimuat
            if (typeof M !== 'undefined') {
                M.updateTextFields();

                // Perbaiki label untuk select dan textarea di input-field
                // (M.updateTextFields hanya menangani input teks)
                $('.gc-modal .input-field select, .gc-modal .input-field textarea').each(function () {
                    var $input = $(this);
                    var $label = $input.siblings('label');
                    if ($input.val() && !$label.hasClass('active')) {
                        $label.addClass('active');
                    }
                });
            }

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

    // ======== Serializer Formulir (menangani checkbox) ========
    function serializeForm($form) {
        var data = {};
        var formArray = $form.serializeArray();

        // Tangani checkbox
        $form.find('input[type="checkbox"]').each(function () {
            var $cb = $(this);
            if ($cb.prop('checked')) {
                if (data[$cb.attr('name')] === undefined) {
                    data[$cb.attr('name')] = [];
                }
                data[$cb.attr('name')].push($cb.val());
            }
        });

        // Timpa dengan nilai serializeArray (untuk input non-checkbox)
        $.each(formArray, function (i, field) {
            if (field.name.indexOf('[]') === -1) {
                data[field.name] = field.value;
            } else {
                var name = field.name.replace('[]', '');
                if (data[name] === undefined) {
                    data[name] = [];
                }
                // Hanya tambahkan jika belum ditambahkan oleh penangan checkbox
                if ($.inArray(field.value, data[name]) === -1) {
                    data[name].push(field.value);
                }
            }
        });

        return data;
    }

    // ======== FormData Upload File ========
    function buildFormData($form) {
        var formData = new FormData($form[0]);
        var mode = $form.data('mode');

        // Tambahkan aksi CRUD (hilang dari FormData)
        formData.append('gc_action', mode);

        // Tangani checkbox yang tidak ada di FormData secara otomatis
        $form.find('input[type="checkbox"]:not(:checked)').each(function () {
            var $cb = $(this);
            var name = $cb.attr('name');
            // Lewati field array (misal, tags[]) — hanya bermakna jika dicentang.
            // Mengatur '0' pada field array membuat PHP melihat ['0'], yang menyebabkan error FK.
            if (name.indexOf('[]') !== -1) {
                return;
            }
            // FormData tidak menyertakan checkbox yang tidak dicentang
            // Kita perlu memastikan field tersebut ada
            if (!formData.has(name)) {
                formData.set(name, '0');
            }
        });

        return formData;
    }

    // ======== Operasi CRUD ========
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

        // Lacak fokus input filter untuk dipulihkan setelah penggantian DOM
        var $focusedFilter = $wrapper.find('.gc-column-filter:focus');
        var wasFilterFocused = $focusedFilter.length > 0;
        var focusedFilterField = wasFilterFocused ? $focusedFilter.data('filter-field') : null;
        var filterCaretPos = wasFilterFocused && $focusedFilter[0].selectionStart !== undefined
            ? $focusedFilter[0].selectionStart
            : -1;

        // Simpan status kolom tersembunyi sebelum refresh (menu kolom akan dibuat ulang)
        var hiddenColumns = [];
        $wrapper.find('.gc-columns-menu input[type="checkbox"]').each(function () {
            if (!$(this).is(':checked')) {
                hiddenColumns.push($(this).data('column'));
            }
        });

        // Kumpulkan filter kolom dari DOM
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
                    // Ikat ulang event
                    bindEvents();
                    // Pulihkan nilai pencarian (crudId berubah saat render ulang via uniqid)
                    var $newSearchInput = $parent.find('.grocery-crud-wrapper .gc-search-input');
                    $newSearchInput.val(search);
                    // Pulihkan filter lanjutan
                    var savedFilters = $wrapper.data('gcAdvancedFilters');
                    if (savedFilters && savedFilters.length) {
                        $wrapper.data('gcAdvancedFilters', savedFilters);
                    }
                    // Pulihkan fokus dan posisi kursor jika sebelumnya difokuskan
                    if (wasFocused) {
                        $newSearchInput.focus();
                        if (caretPos >= 0 && $newSearchInput[0].setSelectionRange) {
                            $newSearchInput[0].setSelectionRange(caretPos, caretPos);
                        }
                    }
                    // Sinkronkan visibilitas tombol hapus (HTML segar selalu menyembunyikannya)
                    var $clearBtn = $newSearchInput.closest('.input-group').find('.gc-search-clear');
                    if (search) {
                        $clearBtn.show();
                    } else {
                        $clearBtn.hide();
                    }
                    // Pulihkan fokus ke input filter jika sebelumnya difokuskan
                    if (wasFilterFocused && focusedFilterField) {
                        var $newFilter = $parent.find('.grocery-crud-wrapper .gc-column-filter[data-filter-field="' + focusedFilterField + '"]');
                        if ($newFilter.length) {
                            $newFilter.focus();
                            if (filterCaretPos >= 0 && $newFilter[0].setSelectionRange) {
                                $newFilter[0].setSelectionRange(filterCaretPos, filterCaretPos);
                            }
                        }
                    }
                    // Isi menu kolom dan filter select dari header tabel
                    var $newWrapper = $parent.find('.grocery-crud-wrapper');
                    // Restore advanced filters data on the new wrapper (gcAdvancedFilters was read before replaceWith)
                    if (advancedFilters && advancedFilters.length) {
                        $newWrapper.data('gcAdvancedFilters', advancedFilters);
                    }
                    populateColumnsAndFilters($newWrapper);
                    initInlineEditing($newWrapper);

                    // Pulihkan status kolom tersembunyi (pengguna tidak mencentang beberapa checkbox sebelum refresh)
                    if (hiddenColumns.length) {
                        hiddenColumns.forEach(function (col) {
                            $newWrapper.find('.gc-columns-menu input[data-column="' + col + '"]')
                                .prop('checked', false)
                                .trigger('change');
                        });
                    }

                    // Pulihkan urutan kolom yang disimpan dari localStorage
                    try {
                        var url = window.location.href;
                        var raw = localStorage.getItem('gc_settings_' + btoa(url));
                        if (raw) {
                            var settings = JSON.parse(raw);
                            if (settings.columnOrder && settings.columnOrder.length) {
                                applyColumnOrder($newWrapper, settings.columnOrder);
                            }
                        }
                    } catch (e) {}
                    // Inisialisasi table-dragger pada tabel yang telah di-refresh
                    initTableDragger($newWrapper);

                    // Pulihkan item panel filter lanjutan dan visibilitas
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
                    _lockedRecordId = id; // Lacak untuk pelepasan kunci saat ditutup
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

    // ======== Isi Kolom & Filter ========
    function populateColumnsAndFilters($wrapper) {
        // Menu kolom: isi checkbox dari header tabel
        var $menu = $wrapper.find('.gc-columns-menu');
        if ($menu.length) {
            $menu.empty();
            $wrapper.find('.gc-table th[data-column]').each(function () {
                var col = $(this).data('column');
                var label = $(this).data('label') || col;
                var isHidden = $(this).hasClass('d-none');
                var $cb = $('<div class="form-check" draggable="true">'
                    + '<input type="checkbox" class="form-check-input" id="col_'
                    + col + '" data-column="' + col + '"' + (isHidden ? '' : ' checked') + '>'
                    + '<label class="form-check-label" for="col_' + col + '">'
                    + $('<span>').text(label).html()
                    + '</label></div>');
                $menu.append($cb);
            });
        }

        // Select filter kolom: isi opsi dari header tabel
        $wrapper.find('.gc-filter-col').each(function () {
            var $select = $(this);
            var currentVal = $select.val();
            // Hanya isi jika kosong (template hanya memiliki opsi placeholder)
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

    /**
     * Urutkan ulang kolom tabel dan menu kolom agar sesuai dengan urutan yang disimpan.
     */
    function applyColumnOrder($wrapper, columnOrder) {
        if (!columnOrder || !columnOrder.length) return;

        // 1. Urutkan ulang checkbox menu kolom
        var $menu = $wrapper.find('.gc-columns-menu');
        if ($menu.length) {
            var items = [];
            $menu.find('.form-check').each(function () {
                var col = $(this).find('.form-check-input').data('column');
                items.push({ col: col, $el: $(this) });
            });
            items.sort(function (a, b) {
                var ia = columnOrder.indexOf(a.col);
                var ib = columnOrder.indexOf(b.col);
                if (ia === -1) ia = 999;
                if (ib === -1) ib = 999;
                return ia - ib;
            });
            // Lepas dan pasang kembali dalam urutan yang diurutkan
            $menu.find('.form-check').detach();
            items.forEach(function (item) {
                $menu.append(item.$el);
            });
        }

        // 2. Urutkan ulang kolom tabel
        var $table = $wrapper.find('.gc-table');
        if (!$table.length) return;

        $table.find('thead tr, tbody tr').each(function () {
            var $row = $(this);
            var $dataCells = $row.find('[data-column]');
            if ($dataCells.length < 2) return;

            // Kumpulkan sel dalam array
            var cells = [];
            $dataCells.each(function () {
                cells.push({ col: $(this).data('column'), $el: $(this) });
            });

            // Urutkan berdasarkan columnOrder
            cells.sort(function (a, b) {
                var ia = columnOrder.indexOf(a.col);
                var ib = columnOrder.indexOf(b.col);
                if (ia === -1) ia = 999;
                if (ib === -1) ib = 999;
                return ia - ib;
            });

            // Temukan referensi: elemen tepat sebelum data-cell pertama
            var $firstDataCell = $dataCells.first();
            var $prev = $firstDataCell.prev();
            var hasPrev = $prev.length > 0;

            // Lepas semua data cell
            $dataCells.detach();

            // Sisipkan sel yang terurut setelah prev (atau di awal baris)
            var sortedEls = cells.map(function (c) { return c.$el[0]; });
            if (hasPrev) {
                $prev.after(sortedEls);
            } else {
                $row.prepend(sortedEls);
            }
        });

        // Pulihkan visibilitas d-none pada kolom yang disembunyikan
        if ($wrapper.find('.gc-columns-menu').length) {
            $wrapper.find('.gc-columns-menu input[type="checkbox"]').each(function () {
                var col = $(this).data('column');
                if (!$(this).is(':checked')) {
                    $table.find('th[data-column="' + col + '"], td[data-column="' + col + '"]').addClass('d-none');
                }
            });
        }
    }

    /**
     * Simpan urutan kolom saat ini dari menu kolom ke localStorage.
     */
    function saveColumnOrder($wrapper) {
        var menu = $wrapper.find('.gc-columns-menu');
        if (!menu.length) return;
        var order = [];
        menu.find('.form-check-input').each(function () {
            order.push($(this).data('column'));
        });
        var url = window.location.href;
        try {
            var key = 'gc_settings_' + btoa(url);
            var raw = localStorage.getItem(key);
            var settings = raw ? JSON.parse(raw) : { columns: {}, filters: [] };
            settings.columnOrder = order;
            localStorage.setItem(key, JSON.stringify(settings));
        } catch (e) {}
    }

    /**
     * Inisialisasi table-dragger pada semua tabel di dalam wrapper yang diberikan.
     * Menggunakan .gc-drag-handle di dalam header agar pengurutan (klik pada teks header)
     * dan penyeretan (klik pada handle) tidak saling bertentangan.
     */
    function initTableDragger($wrapper) {
        console.log('[GC_TD] initTableDragger called, tableDragger=',
            typeof tableDragger, 'wrapper=', $wrapper.length);
        // table-dragger via CDN adalah UMD: fungsi sebenarnya adalah .default
        var draggerFn = (tableDragger && tableDragger.default) || tableDragger;
        if (typeof draggerFn !== 'function') {
            console.warn('[GC_TD] tableDragger not available, obj=', tableDragger);
            return;
        }
        // Hancurkan instance sebelumnya pada wrapper ini
        var prev = $wrapper.data('gcDragger');
        if (prev) { try { prev.destroy(); } catch(e) {} }
        var $table = $wrapper.find('.gc-table');
        console.log('[GC_TD] table found:', $table.length);
        if (!$table.length) return;
        try {
            // Tambahkan gagang seret ke setiap header data-column di baris header pertama
            var $handles = $table.find('thead tr:first-child th[data-column]');
            console.log('[GC_TD] data-column headers:', $handles.length);
            if ($handles.length < 2) {
                console.warn('[GC_TD] not enough draggable columns');
            }
            $handles.each(function () {
                if (!$(this).find('.gc-drag-handle').length) {
                    $('<span class="gc-drag-handle">⠿</span> ').prependTo(this);
                }
            });
            var handleCount = $table.find('.gc-drag-handle').length;
            console.log('[GC_TD] handles added:', handleCount);
            var dragger = draggerFn($table[0], {
                mode: 'column',
                dragHandler: '.gc-drag-handle',
                animation: 200
            });
            $wrapper.data('gcDragger', dragger);
            console.log('[GC_TD] table-dragger initialized');
            dragger.on('drop', function (oldIndex, newIndex, el, mode) {
                console.log('[GC_TD] drop event', oldIndex, newIndex, mode);
                // table-dragger sudah mengurutkan ulang DOM tabel
                // Baca urutan kolom baru dari header tabel
                var newOrder = [];
                $table[0].querySelectorAll('thead th[data-column]').forEach(function (th) {
                    newOrder.push(th.getAttribute('data-column'));
                });
                if (!newOrder.length) return;
                // Sinkronkan urutan menu kolom agar sesuai
                var $menu = $wrapper.find('.gc-columns-menu');
                if ($menu.length) {
                    var sorted = [];
                    newOrder.forEach(function (col) {
                        var $item = $menu.find('.form-check-input[data-column="' + col + '"]').closest('.form-check');
                        if ($item.length) sorted.push($item[0]);
                    });
                    // Tambahkan item yang tersisa di akhir
                    $menu.find('.form-check').each(function () {
                        if (sorted.indexOf(this) === -1) sorted.push(this);
                    });
                    $menu.find('.form-check').detach();
                    sorted.forEach(function (el) { $menu.append(el); });
                }
                // Simpan urutan
                saveColumnOrder($wrapper);
            });
        } catch (e) {
            console.warn('[GC] table-dragger init failed:', e);
        }
    }

    function submitForm($form) {
        var $modal = $form.closest('.modal');
        var $submitBtn = $form.find('button[type="submit"]');
        var mode = $form.data('mode');
        var hasFile = $form.find('input[type="file"]').length > 0;

        // Nonaktifkan tombol
        $submitBtn.prop('disabled', true).addClass('btn-gc-loading');

        syncRichtextEditors($modal);

        var ajaxConfig = {
            url: window.location.href,
            method: 'POST',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    _lockedRecordId = null; // Lock sudah dilepaskan di sisi server
                    GcModal.hide();
                    showAlert(response.message, 'success');
                    // Refresh daftar
                    refreshList($('.grocery-crud-wrapper'));
                } else {
                    // Tampilkan error validasi
                    if (response.errors) {
                        // Hapus error sebelumnya
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
            // $form.serialize() melewatkan input yang dinonaktifkan DAN checkbox yang tidak dicentang.
            // Kita perlu memastikan checkbox yang tidak dicentang dikirim sebagai '0'.
            var formData = $form.serialize();
            $form.find('input[type="checkbox"]:not(:checked)').each(function () {
                var $cb = $(this);
                var name = $cb.attr('name');
                // Lewati field array (misal, tags[]) — hanya bermakna jika dicentang
                if (name.indexOf('[]') !== -1) return;
                // Hanya tambahkan jika belum diserialisasi (misal, melalui sibling tersembunyi)
                if (formData.indexOf(name + '=') === -1) {
                    formData += '&' + name + '=0';
                }
            });
            ajaxConfig.data = formData + '&gc_action=' + mode;
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

        // Jika sudah dimuat, cukup toggle
        var $content = $subRow.find('.gc-subgrid-content');
        var $table = $content.find('.gc-subgrid-inner');
        if ($table.length > 0) {
            $subRow.toggle();
            $btn.find('i').toggleClass('bi-chevron-right bi-chevron-down');
            return;
        }

        // Muat via AJAX
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
        if (format === 'print') {
            // Tampilan cetak: buka di jendela baru
            window.open(
                window.location.pathname + '?gc_action=print_view',
                '_blank',
                'width=1200,height=800,scrollbars=yes'
            );
        } else {
            // CSV, Excel, PDF: tampilkan modal pemilih kolom
            showExportColumnSelector($btn, format);
        }
    }

    function showExportColumnSelector($btn, format) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        var $table = $wrapper.find('.gc-table');
        if (!$table.length) return;

        // Kumpulkan kolom dari header tabel
        var columns = [];
        $table.find('thead th[data-column]').each(function () {
            var $th = $(this);
            var col = $th.data('column');
            var label = $th.data('label') || col;
            var isVisible = !$th.hasClass('d-none');
            columns.push({ name: col, label: label, visible: isVisible });
        });

        if (columns.length === 0) return;

        // Label format
        var fmtLabels = { csv: 'CSV', excel: 'Excel', pdf: 'PDF' };
        var fmtLabel = fmtLabels[format] || format.toUpperCase();

        // Bangun HTML modal
        var html = '<div class="p-3">';
        html += '<h5 class="mb-3 fw-bold"><i class="bi bi-download me-2"></i>Select Columns to Export</h5>';
        html += '<p class="text-muted small mb-3">Choose which columns to include in the ' + fmtLabel + ' export.</p>';
        html += '<div class="mb-3">';

        // Pilih Semua / Batalkan Pilih Semua
        html += '<div class="mb-2">';
        html += '<button type="button" class="btn btn-sm btn-outline-secondary me-2 gc-export-selall">Select All</button>';
        html += '<button type="button" class="btn btn-sm btn-outline-secondary gc-export-deselall">Deselect All</button>';
        html += '</div>';

        // Checkbox kolom
        html += '<div class="gc-export-columns-list">';
        for (var i = 0; i < columns.length; i++) {
            var col = columns[i];
            html += '<div class="form-check gc-export-col-item">';
            html += '<input class="form-check-input browser-default gc-export-col-cb" type="checkbox" id="expcol_' + i + '" value="' + col.name + '"' + (col.visible ? ' checked' : '') + '>';
            html += '<label class="form-check-label" for="expcol_' + i + '">' + col.label + '</label>';
            html += '</div>';
        }
        html += '</div>';

        // Lingkup ekspor: Semua catatan vs Hanya yang difilter
        var hasFilters = $wrapper.find('.gc-filter-item').length > 0
            || ($wrapper.data('gcAdvancedFilters') && $wrapper.data('gcAdvancedFilters').length > 0);
        html += '<div class="mb-3 border-top pt-3">';
        html += '<label class="fw-bold small mb-2"><i class="bi bi-funnel me-1"></i>Export Scope</label>';
        html += '<div class="form-check">';
        html += '<input class="form-check-input" type="radio" name="export_scope" id="exp_scope_all" value="all" checked>';
        html += '<label class="form-check-label" for="exp_scope_all">All Records</label>';
        html += '</div>';
        html += '<div class="form-check">';
        html += '<input class="form-check-input" type="radio" name="export_scope" id="exp_scope_filtered" value="filtered"' + (hasFilters ? '' : ' disabled') + '>';
        html += '<label class="form-check-label' + (hasFilters ? '' : ' text-muted') + '" for="exp_scope_filtered">Only Filtered Records' + (hasFilters ? '' : ' (no active filters)') + '</label>';
        html += '</div>';
        html += '</div>';

        // Tombol footer
        html += '<div class="d-flex justify-content-end gap-2 border-top pt-3">';
        html += '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>';
        html += '<button type="button" class="btn btn-primary gc-export-submit-btn" data-format="' + format + '"><i class="bi bi-download me-1"></i>Export ' + fmtLabel + '</button>';
        html += '</div></div>';

        var $modal = GcModal.show(html);

        // Pilih Semua
        $modal.find('.gc-export-selall').on('click', function () {
            $modal.find('.gc-export-col-cb').prop('checked', true);
        });

        // Batalkan Pilih Semua
        $modal.find('.gc-export-deselall').on('click', function () {
            $modal.find('.gc-export-col-cb').prop('checked', false);
        });

        // Kirim ekspor
        $modal.find('.gc-export-submit-btn').on('click', function () {
            var selectedFormat = $(this).data('format');
            var selectedColumns = [];
            $modal.find('.gc-export-col-cb:checked').each(function () {
                selectedColumns.push($(this).val());
            });

            if (selectedColumns.length === 0) {
                showAlert('Please select at least one column.', 'warning');
                return;
            }

            GcModal.remove();
            showLoading();

            // Bangun formulir POST untuk memicu unduhan file
            var $form = $('<form method="post" style="display:none"></form>');
            $form.attr('action', window.location.href.split('?')[0]);
            $form.append('<input type="hidden" name="gc_action" value="export">');
            $form.append('<input type="hidden" name="format" value="' + selectedFormat + '">');
            for (var j = 0; j < selectedColumns.length; j++) {
                $form.append('<input type="hidden" name="columns[]" value="' + selectedColumns[j] + '">');
            }
            // Berikan lingkup ekspor
            var exportScope = $modal.find('input[name="export_scope"]:checked').val() || 'all';
            $form.append('<input type="hidden" name="export_scope" value="' + exportScope + '">');
            // Jika difilter, berikan filter saat ini
            if (exportScope === 'filtered') {
                // Filter kolom (dari input filter di atas kolom tabel)
                var filters = {};
                $wrapper.find('.gc-column-filter').each(function () {
                    var field = $(this).data('field');
                    var val = $(this).val();
                    if (field && val) {
                        filters[field] = val;
                    }
                });
                if (Object.keys(filters).length > 0) {
                    $form.append('<input type="hidden" name="export_filters" value=\'' + JSON.stringify(filters) + '\'>');
                }
                // Filter lanjutan (dari panel filter)
                var advancedFilters = $wrapper.data('gcAdvancedFilters') || [];
                if (advancedFilters.length > 0) {
                    $form.append('<input type="hidden" name="export_advanced_filters" value=\'' + JSON.stringify(advancedFilters) + '\'>');
                }
            }
            $('body').append($form);
            $form.submit();

            setTimeout(function () {
                hideLoading();
                $form.remove();
            }, 3000);
        });
    }

    // ======== Alur Kerja Impor ========
    var _importing = false;

    function loadImportForm($btn) {
        if (_importing) return;
        _importing = true;
        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: { gc_action: 'import_form' },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    var $modal = GcModal.show(response.html);
                    bindImportEvents($modal);
                } else {
                    showAlert(response.message || 'Failed to load import form.', 'danger');
                }
            },
            error: function () {
                showAlert('An error occurred.', 'danger');
            },
            complete: function () {
                hideLoading();
                _importing = false;
            }
        });
    }

    function bindImportEvents($modal) {
        // Tombol tutup
        $modal.on('click', '.gc-form-close', function (e) {
            e.preventDefault();
            GcModal.hide();
        });

        // Tutup saat backdrop diklik
        $modal.on('hidden.bs.modal', function () {
            GcModal.remove();
        });

        // Tombol jelajah -> picu input file
        $modal.on('click', '.gc-import-browse-btn', function () {
            $modal.find('.gc-import-file-input').click();
        });

        // Klik pada dropzone -> picu input file (lewati jika klik berasal dari input file itu sendiri)
        $modal.on('click', '.gc-import-dropzone', function (e) {
            if ($(e.target).closest('.gc-import-file-input, .gc-import-browse-btn').length) return;
            $modal.find('.gc-import-file-input').click();
        });

        // File dipilih -> unggah
        $modal.on('change', '.gc-import-file-input', function () {
            var file = this.files[0];
            if (!file) return;

            // Tampilkan nama file
            $modal.find('.gc-import-filename').text(file.name).removeClass('d-none');

            // Unggah
            uploadImportFile($modal, file);
        });

        // Jalankan impor
        $modal.on('click', '.gc-import-execute-btn', function () {
            executeImport($modal);
        });

        // Unduh template dengan field yang dipilih
        $modal.on('click', '.gc-template-download-selected', function () {
            var fields = [];
            $modal.find('.gc-template-field-cb:checked').each(function () {
                fields.push($(this).val());
            });
            if (fields.length === 0) {
                showAlert('Please select at least one field.', 'warning');
                return;
            }
            var baseUrl = window.location.href.split('?')[0];
            var params = $.param({ gc_action: 'import_template', fields: fields });
            window.open(baseUrl + '?' + params, '_blank');
        });
    }

    function uploadImportFile($modal, file) {
        var formData = new FormData();
        formData.append('import_file', file);
        formData.append('gc_action', 'import_upload');

        // Tampilkan status unggah
        $modal.find('.gc-import-dropzone').addClass('d-none');
        $modal.find('.gc-import-filename').addClass('d-none');
        $modal.find('.gc-import-uploading').removeClass('d-none');

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                $modal.find('.gc-import-uploading').addClass('d-none');
                $modal.find('.gc-import-dropzone').removeClass('d-none');

                if (response.success) {
                    showMappingUI($modal, response);
                } else {
                    showAlert(response.message || 'Failed to parse file.', 'danger');
                }
            },
            error: function () {
                $modal.find('.gc-import-uploading').addClass('d-none');
                $modal.find('.gc-import-dropzone').removeClass('d-none');
                showAlert('An error occurred while uploading.', 'danger');
            }
        });
    }

    function showMappingUI($modal, data) {
        var headers = data.headers || [];
        var preview = data.preview || [];
        var mapping = data.mapping || [];
        var fields = data.fields || [];
        var fieldLabels = data.fieldLabels || {};
        var totalRows = data.totalRows || 0;

        // Bangun tabel pemetaan
        var $mappingBody = $modal.find('.gc-import-mapping-table tbody');
        $mappingBody.empty();

        headers.forEach(function (header, index) {
            var mappedField = mapping[index] || '';
            var sampleData = preview.length > 0 ? (preview[0][header] || '') : '';
            var $row = $('<tr></tr>');
            $row.append('<td><strong>' + $('<span>').text(header).html() + '</strong></td>');

            var $select = $('<select class="form-select form-select-sm"></select>');
            $select.append('<option value="">-- ' + $('<span>').text(data.lang_not_mapped || 'Not mapped').html() + ' --</option>');

            fields.forEach(function (field) {
                var label = fieldLabels[field] || field;
                var $opt = $('<option></option>').attr('value', field).text(label);
                if (field === mappedField) {
                    $opt.prop('selected', true);
                }
                $select.append($opt);
            });

            var $td = $('<td></td>').append($select);
            $row.append($td);
            $row.append('<td><code class="small">' + $('<span>').text(sampleData).html() + '</code></td>');
            $mappingBody.append($row);
        });

        // Bangun tabel pratinjau
        var $previewHead = $modal.find('.gc-import-preview-table thead tr');
        var $previewBody = $modal.find('.gc-import-preview-table tbody');
        $previewHead.empty();
        $previewBody.empty();

        headers.forEach(function (header) {
            $previewHead.append('<th>' + $('<span>').text(header).html() + '</th>');
        });

        preview.forEach(function (row) {
            var $row = $('<tr></tr>');
            headers.forEach(function (header) {
                $row.append('<td class="small">' + $('<span>').text(row[header] || '').html() + '</td>');
            });
            $previewBody.append($row);
        });

        // Tampilkan total baris
        $modal.find('.gc-import-preview-info').text(data.total_rows_label || 'Total rows in file') + ': ' + totalRows;
        $modal.find('.gc-import-preview-info').html(
            '<span class="text-muted">' + (data.total_rows_label || 'Total rows in file') + ': <strong>' + totalRows + '</strong></span>'
        );

        // Tampilkan tombol eksekusi
        $modal.find('.gc-import-execute-btn').removeClass('d-none');

        // Tampilkan langkah pemetaan
        $modal.find('.gc-import-step[data-step="mapping"]').removeClass('d-none');

        // Simpan data file untuk nanti
        $modal.data('importData', {
            totalRows: totalRows,
            preview: preview,
            headers: headers,
            mapping: mapping
        });
    }

    function executeImport($modal) {
        var importData = $modal.data('importData');
        if (!importData) return;

        // Baca pemetaan saat ini dari UI
        var mapping = [];
        $modal.find('.gc-import-mapping-table tbody tr').each(function () {
            var $select = $(this).find('select');
            mapping.push($select.val() || null);
        });

        var totalRows = importData.totalRows || 0;
        var msg = (importData.confirm_label || 'Are you sure you want to import {total} records?').replace('{total}', totalRows);
        if (!confirm(msg)) return;

        // Ambil data pratinjau sebagai baris (array terindeks header)
        var rows = importData.preview.map(function (row) {
            return importData.headers.map(function (header) {
                return row[header] || '';
            });
        });

        // Nonaktifkan tombol
        var $btn = $modal.find('.gc-import-execute-btn');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Importing...');

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                gc_action: 'import_execute',
                rows: JSON.stringify(rows),
                mapping: JSON.stringify(mapping)
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    GcModal.hide();
                    var message = response.message || 'Import completed.';
                    if (response.errors && response.errors.length > 0) {
                        message += ' (' + response.errors.length + ' errors)';
                    }
                    showAlert(message, response.imported > 0 ? 'success' : 'danger');
                    refreshList($('.grocery-crud-wrapper'));
                } else {
                    showAlert(response.message || 'Import failed.', 'danger');
                    $btn.prop('disabled', false).text('Import Data');
                }
            },
            error: function () {
                showAlert('An error occurred during import.', 'danger');
                $btn.prop('disabled', false).text('Import Data');
            }
        });
    }

    // ======== Pengikatan Event ========
    function bindEvents() {
        // Tombol impor
        $(document).off('click', '.btn-gc-import').on('click', '.btn-gc-import', function (e) {
            e.preventDefault();
            loadImportForm($(this));
        });

        // Tombol tambah
        $(document).off('click', '.btn-gc-add').on('click', '.btn-gc-add', function (e) {
            e.preventDefault();
            loadAddForm($(this));
        });

        // Tombol edit
        $(document).off('click', '.btn-gc-edit').on('click', '.btn-gc-edit', function (e) {
            e.preventDefault();
            loadEditForm($(this));
        });

        // Tombol hapus
        $(document).off('click', '.btn-gc-delete').on('click', '.btn-gc-delete', function (e) {
            e.preventDefault();
            deleteRecord($(this));
        });

        // Tombol pulihkan (tampilan sampah)
        $(document).off('click', '.btn-gc-restore').on('click', '.btn-gc-restore', function (e) {
            e.preventDefault();
            restoreRecord($(this));
        });

        // Tombol daftar sampah
        $(document).off('click', '.gc-btn-trash').on('click', '.gc-btn-trash', function (e) {
            e.preventDefault();
            loadTrashList($(this));
        });

        // Tombol daftar aktif (dari tampilan sampah)
        $(document).off('click', '.gc-btn-active').on('click', '.gc-btn-active', function (e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        });

        // Toggle perluas/ciutkan sub-grid
        $(document).off('click', '.gc-subgrid-toggle').on('click', '.gc-subgrid-toggle', function (e) {
            e.preventDefault();
            loadSubGrid($(this));
        });

        // Tautan paginasi
        $(document).off('click', '.gc-page-link').on('click', '.gc-page-link', function (e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var page = $(this).data('page');
            $wrapper.data('currentPage', page);
            refreshList($wrapper);
        });

        // Pencarian - waktu nyata pada keyup (debounced)
        $(document).off('keyup', '.gc-search-input').on('keyup', '.gc-search-input', $.debounce(function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        }, 400));

        // Pencarian - langsung pada tombol Enter
        $(document).off('keydown', '.gc-search-input').on('keydown', '.gc-search-input', function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                var $wrapper = $(this).closest('.grocery-crud-wrapper');
                $wrapper.data('currentPage', 1);
                refreshList($wrapper);
            }
        });

        // Tampilkan/sembunyikan tombol hapus pencarian saat input berubah
        $(document).off('input', '.gc-search-input').on('input', '.gc-search-input', function () {
            var $clearBtn = $(this).closest('.input-group').find('.gc-search-clear');
            if ($(this).val()) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }
        });

        // Tombol hapus pencarian
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

        // ======== Filter Kolom ========
        var filterTimer = null;
        $(document).off('change', '.gc-column-filter').on('change', '.gc-column-filter', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        });
        $(document).off('input', '.gc-column-filter').on('input', '.gc-column-filter', function () {
            var $self = $(this);
            // Hanya debounce input teks (select menggunakan 'change' di atas)
            if ($self.is('select')) return;
            clearTimeout(filterTimer);
            filterTimer = setTimeout(function () {
                var $wrapper = $self.closest('.grocery-crud-wrapper');
                $wrapper.data('currentPage', 1);
                refreshList($wrapper);
            }, 400);
        });

        // ======== Aksi Batch ========
        // Checkbox pilih semua
        $(document).off('change', '.gc-select-all').on('change', '.gc-select-all', function () {
            var isChecked = $(this).prop('checked');
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $wrapper.find('.gc-row-checkbox').prop('checked', isChecked);
            updateBatchToolbar($wrapper);
        });

        // Checkbox baris
        $(document).off('change', '.gc-row-checkbox').on('change', '.gc-row-checkbox', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var allChecked = $wrapper.find('.gc-row-checkbox').length === $wrapper.find('.gc-row-checkbox:checked').length;
            $wrapper.find('.gc-select-all').prop('checked', allChecked);
            updateBatchToolbar($wrapper);
        });

        // Tombol aksi batch
        $(document).off('click', '.gc-batch-action').on('click', '.gc-batch-action', function (e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var actionId = $(this).data('batch-action');
            var selectedIds = [];
            $wrapper.find('.gc-row-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
            });
            if (selectedIds.length === 0) return;

            // Deteksi jika kita dalam tampilan sampah (tombol "Catatan Aktif" hanya muncul di sana)
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

            // Saat dalam tampilan sampah, delete_selected harus menghapus permanen
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

        // Pembantu bilah alat batch
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

        // Header kolom yang dapat diurutkan
        $(document).off('click', '.gc-sortable').on('click', '.gc-sortable', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var field = $(this).data('sort-field');
            var dir = $(this).data('sort-dir');
            $wrapper.data('sortField', field);
            $wrapper.data('sortDir', dir);
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        });

        // Ekspor
        $(document).off('click', '[data-export]').on('click', '[data-export]', function (e) {
            e.preventDefault();
            handleExport($(this), $(this).data('export'));
        });

        // ======== Penampil Log Aktivitas ========
        $(document).off('click', '.gc-btn-activity-log').on('click', '.gc-btn-activity-log', function (e) {
            e.preventDefault();
            loadActivityLogViewer($(this));
        });

        $(document).off('click', '.gc-btn-back-to-list').on('click', '.gc-btn-back-to-list', function (e) {
            e.preventDefault();
            goBackToCrudList($(this));
        });

        $(document).off('click', '.gc-alf-apply').on('click', '.gc-alf-apply', function (e) {
            e.preventDefault();
            applyActivityLogFilter($(this));
        });

        $(document).off('click', '.gc-log-page-link').on('click', '.gc-log-page-link', function (e) {
            e.preventDefault();
            var page = parseInt($(this).data('page'), 10);
            if (page > 0) {
                loadActivityLogPage($(this), page);
            }
        });

        $(document).off('click', '.gc-log-sortable').on('click', '.gc-log-sortable', function (e) {
            e.preventDefault();
            sortActivityLog($(this));
        });

        $(document).off('click', '.gc-log-detail').on('click', '.gc-log-detail', function (e) {
            e.preventDefault();
            var logId = $(this).data('log-id');
            if (logId) {
                showActivityLogDetail($(this), logId);
            }
        });

        // ======== Tampilan Kalender ========
        $(document).off('click', '.gc-btn-calendar').on('click', '.gc-btn-calendar', function (e) {
            e.preventDefault();
            loadCalendarView($(this));
        });

        $(document).off('click', '.gc-btn-table-view').on('click', '.gc-btn-table-view', function (e) {
            e.preventDefault();
            goBackToTableView($(this));
        });

        // ======== Toggle Dropdown Kolom ========
        $(document).off('change', '.gc-columns-menu input[type="checkbox"]').on('change', '.gc-columns-menu input[type="checkbox"]', function () {
            var col = $(this).data('column');
            var $table = $(this).closest('.grocery-crud-wrapper').find('.gc-table');
            if ($(this).is(':checked')) {
                $table.find('th[data-column="' + col + '"], td[data-column="' + col + '"]').removeClass('d-none');
            } else {
                $table.find('th[data-column="' + col + '"], td[data-column="' + col + '"]').addClass('d-none');
            }
        });

        // ======== Panel Filter ========
        // Toggle panel filter
        $(document).off('click', '.gc-filter-btn').on('click', '.gc-filter-btn', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var $panel = $wrapper.find('.gc-filter-panel');
            $panel.toggle();
        });

        // Tambah baris filter
        $(document).off('click', '.gc-filter-add').on('click', '.gc-filter-add', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var $rows = $wrapper.find('.gc-filter-rows');
            var $template = $rows.find('.gc-filter-item-template').clone().removeClass('gc-filter-item-template').addClass('gc-filter-item').show();
            $template.find('input').val('');
            $template.find('select').prop('selectedIndex', 0);
            $rows.append($template);
        });

        // Hapus baris filter
        $(document).off('click', '.gc-filter-item-remove').on('click', '.gc-filter-item-remove', function () {
            $(this).closest('.gc-filter-item').remove();
        });

        // Terapkan filter
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

        // Hapus filter
        $(document).off('click', '.gc-filter-clear').on('click', '.gc-filter-clear', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            $wrapper.find('.gc-filter-item').remove();
            $wrapper.find('.gc-filter-panel').hide();
            $wrapper.removeData('gcAdvancedFilters');
            $wrapper.data('currentPage', 1);
            refreshList($wrapper);
        });

        // ======== Urut Ulang Kolom via table-dragger ========
        // Karena input/label memiliki pointer-events:none, tangani toggle via klik pada div .form-check
        $(document).off('click', '.gc-columns-menu .form-check').on('click', '.gc-columns-menu .form-check', function () {
            var $input = $(this).find('.form-check-input');
            $input.prop('checked', !$input.is(':checked')).trigger('change');
        });

        // ======== Simpan/Muat/Atur Ulang Pengaturan ========
        // Simpan pengaturan
        $(document).off('click', '.gc-settings-save').on('click', '.gc-settings-save', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var url = window.location.href;
            var settings = {
                columns: {},
                columnOrder: [],
                filters: $wrapper.data('gcAdvancedFilters') || []
            };
            $wrapper.find('.gc-columns-menu input[type="checkbox"]').each(function () {
                settings.columns[$(this).data('column')] = $(this).is(':checked');
                settings.columnOrder.push($(this).data('column'));
            });
            try {
                localStorage.setItem('gc_settings_' + btoa(url), JSON.stringify(settings));
                showAlert('Settings saved.', 'success');
            } catch (e) {
                showAlert('Could not save settings.', 'danger');
            }
        });

        // Muat pengaturan
        $(document).off('click', '.gc-settings-load').on('click', '.gc-settings-load', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var url = window.location.href;
            try {
                var raw = localStorage.getItem('gc_settings_' + btoa(url));
                if (!raw) { showAlert('No saved settings found.', 'warning'); return; }
                var settings = JSON.parse(raw);
                // Pulihkan urutan kolom
                if (settings.columnOrder && settings.columnOrder.length) {
                    applyColumnOrder($wrapper, settings.columnOrder);
                }
                // Pulihkan visibilitas kolom
                if (settings.columns) {
                    $wrapper.find('.gc-columns-menu input[type="checkbox"]').each(function () {
                        var col = $(this).data('column');
                        if (settings.columns[col] !== undefined) {
                            $(this).prop('checked', settings.columns[col]).trigger('change');
                        }
                    });
                }
                // Pulihkan filter
                if (settings.filters && settings.filters.length) {
                    $wrapper.data('gcAdvancedFilters', settings.filters);
                    refreshList($wrapper);
                }
                showAlert('Settings loaded.', 'success');
            } catch (e) {
                showAlert('Could not load settings.', 'danger');
            }
        });

        // Atur ulang pengaturan
        $(document).off('click', '.gc-settings-reset').on('click', '.gc-settings-reset', function () {
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var url = window.location.href;
            try {
                localStorage.removeItem('gc_settings_' + btoa(url));
                // Atur ulang urutan kolom ke aslinya (dari header tabel)
                var order = [];
                $wrapper.find('.gc-table th[data-column]').each(function () {
                    order.push($(this).data('column'));
                });
                if (order.length) {
                    applyColumnOrder($wrapper, order);
                }
                $wrapper.find('.gc-columns-menu input[type="checkbox"]').each(function () {
                    $(this).prop('checked', true).trigger('change');
                });
                $wrapper.removeData('gcAdvancedFilters');
                $wrapper.find('.gc-filter-item').remove();
                $wrapper.find('.gc-filter-panel').hide();
                showAlert('Settings reset to defaults.', 'success');
            } catch (e) {}
        });

        // Penampil gambar - klik thumbnail untuk memperbesar
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

            // Sesuaikan dialog dengan ukuran asli gambar setelah dimuat
            $modalImg.on('load', function () {
                var $dialog = $modal.find('.modal-dialog');
                var imgW = this.naturalWidth;
                var imgH = this.naturalHeight;

                // Batasi di viewport - beberapa margin
                var maxW = window.innerWidth * 0.9;
                var maxH = window.innerHeight * 0.85;

                if (imgW > maxW || imgH > maxH) {
                    // Gambar lebih besar dari viewport — biarkan CSS menanganinya
                    $modalImg.show();
                } else {
                    // Gambar muat — sesuaikan dialog dengan gambar
                    $modalImg.css({display:'block', width: imgW + 'px', height: 'auto'});
                    $dialog.css('max-width', (imgW + 40) + 'px');
                }

                // Pusatkan ulang modal setelah perubahan ukuran konten
                $modal[0]._isShown && $modal.modal('handleUpdate');
            });

            // Jika sudah di-cache, picu pemuatan secara manual
            if ($modalImg[0].complete) {
                $modalImg.trigger('load');
            } else {
                // Pastikan img ditampilkan meskipun pemuatan gagal
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

        // ======== Field Repeater ========
        // Tambah item
        $(document).off('click', '.gc-repeater-add').on('click', '.gc-repeater-add', function () {
            var $btn = $(this);
            var $container = $btn.closest('.gc-repeater-container');
            var $template = $container.find('.gc-repeater-template');
            var index = $container.find('.gc-repeater-item').not($container.find('.gc-repeater-template .gc-repeater-item')).length;

            var html = $template.html().replace(/__INDEX__/g, index);
            html = html.replace(/ disabled/g, '');  // Hapus disabled dari item yang dikloning
            $btn.before(html);
        });

        // Hapus item
        $(document).off('click', '.gc-repeater-remove').on('click', '.gc-repeater-remove', function () {
            $(this).closest('.gc-repeater-item').remove();
        });

        // Isi menu kolom dan filter select untuk wrapper yang ada
        $(document).find('.grocery-crud-wrapper').each(function () {
            populateColumnsAndFilters($(this));
            initInlineEditing($(this));
        });

        // Dropdown bilah alat GC: lewati sistem dropdown bawaan framework (Materialize, Bootstrap, dll.)
        // Hancurkan instance Materialize pada tombol bilah alat GC terlebih dahulu (Materialize menginisialisasi otomatis pada DOMContentLoaded
        // dan penangan kliknya menggunakan stopPropagation, memblokir penangan tingkat dokumen GC)
        if (typeof M !== 'undefined' && typeof M.Dropdown !== 'undefined') {
            $('.grocery-crud-wrapper .dropdown-trigger').each(function () {
                var instance = M.Dropdown.getInstance(this);
                if (instance) {
                    instance.destroy();
                }
            });
        }

        // Inisialisasi semua dropdown GC sebagai tersembunyi (misal, CSS Materialize tidak menyembunyikan .dropdown-content)
        $('.grocery-crud-wrapper .dropdown-content').hide();

        // Penangan klik langsung untuk setiap pemicu dropdown (mengikat langsung ke elemen, menghindari masalah delegasi)
        $('.grocery-crud-wrapper a.dropdown-trigger[data-target]').off('click.gc-dd').on('click.gc-dd', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var targetId = $btn.data('target');
            var $target = $('#' + targetId);
            if (!$target.length) return;

            // Debounce: abaikan jika dropdown ini baru saja diinteraksi (mencegah tembakan ganda)
            var now = Date.now();
            var lastToggle = $target.data('gc-last-toggle') || 0;
            if (now - lastToggle < 100) {
                $target.data('gc-last-toggle', now);
                return;
            }
            $target.data('gc-last-toggle', now);

            // Jika target ini sudah terbuka, tutup
            if ($target.is(':visible')) {
                $target.hide();
                return;
            }

            // Tutup semua dropdown lain di wrapper ini
            var $wrapper = $btn.closest('.grocery-crud-wrapper');
            $wrapper.find('.dropdown-content').not($target).hide();

            // Tampilkan target dengan posisi (timpa default Materialize: opacity, transform, top, left)
            var el = $target[0];
            el.style.display = 'block';
            el.style.opacity = '1';
            el.style.transform = 'none';
            el.style.pointerEvents = 'auto';
            el.style.position = 'absolute';
            el.style.zIndex = 9999;
            el.style.marginTop = '0';

            // Posisikan dropdown di bawah tombol yang diklik
            var btnPos = $btn.position();
            var $toolbar = $btn.closest('.right, .card-tools, .card-header-icon');
            el.style.top = (btnPos.top + $btn.outerHeight() + 4) + 'px';
            el.style.left = btnPos.left + 'px';
            el.style.right = 'auto';

            // Cegah dropdown meluber ke tepi kanan bilah alat
            var dropdownWidth = $target.outerWidth() || 200;
            var toolbarWidth = $toolbar.outerWidth() || 0;
            if (toolbarWidth && (btnPos.left + dropdownWidth > toolbarWidth)) {
                el.style.left = Math.max(0, toolbarWidth - dropdownWidth) + 'px';
            }
        });

        // Tutup dropdown saat klik di luar (delegasi aman untuk tingkat dokumen)
        $(document).off('click.gc-dd-close').on('click.gc-dd-close', function (e) {
            if (!$(e.target).closest('.grocery-crud-wrapper a.dropdown-trigger[data-target]').length
                && !$(e.target).closest('.grocery-crud-wrapper .dropdown-content:visible').length) {
                $('.grocery-crud-wrapper .dropdown-content').hide();
            }
        });

        // ======== File Manager ========
        $(document).off('click', '.gc-btn-file-manager').on('click', '.gc-btn-file-manager', function (e) {
            e.preventDefault();
            loadFileManager($(this));
        });
    }

    // ======== Kondisi Formulir Dinamis (Bergantung Pada) ========
    /**
     * Inisialisasi visibilitas/ pengaktifan field dinamis dependsOn.
     * Field dengan atribut data-depends-on akan tampil/sembunyi atau aktif/nonaktif
     * berdasarkan nilai dari field pengontrolnya.
     */
    function initDependsOn($modal) {
        $modal.find('[data-depends-on]').each(function () {
            var $field = $(this);
            var config = $field.data('dependsOn');
            if (!config) return;

            var $form = $field.closest('.gc-form');
            if (!$form.length) return;

            // Temukan field pengontrol — tangani berbagai jenis input
            var $controller = $form.find('[name="' + config.field + '"]');
            if (!$controller.length) {
                // Coba dengan akhiran [] (untuk array checkbox)
                $controller = $form.find('[name="' + config.field + '[]"]');
            }
            if (!$controller.length) return;

            function normalizeConfigValue(val) {
                // Normalisasi nilai konfigurasi boolean ke '1'/'0' untuk perbandingan checkbox
                // String(true) = 'true' di JS, tetapi checkbox dicentang = '1'
                if (typeof val === 'boolean') {
                    return val ? '1' : '0';
                }
                return String(val);
            }

            function updateDependsOn() {
                var controllerValue;
                if ($controller.is(':checkbox') || $controller.is('[type="checkbox"]')) {
                    controllerValue = $controller.is(':checked') ? '1' : '0';
                } else {
                    controllerValue = $controller.val();
                }

                var match = String(controllerValue) === normalizeConfigValue(config.value);

                if (config.action === 'enable') {
                    // Aktifkan/nonaktifkan input tanpa menyembunyikan
                    $field.find('input, select, textarea, button').prop('disabled', !match);
                    // Isyarat visual: redupkan field saat dinonaktifkan
                    $field.toggleClass('gc-depends-disabled', !match);
                } else {
                    // Default 'show': sembunyikan/tampilkan seluruh grup field
                    $field.toggle(match);
                    // Nonaktifkan input saat tersembunyi agar tidak terkirim
                    $field.find('input, select, textarea, button').prop('disabled', !match);
                }
            }

            // Dengarkan perubahan pada pengontrol
            $controller.on('change.dependsOn', updateDependsOn);

            // Untuk input teks, dengarkan juga keyup untuk umpan balik langsung
            if ($controller.is('input[type="text"], input[type="number"], input[type="email"], input[type="tel"], input[type="url"], input[type="password"]')) {
                $controller.on('keyup.dependsOn', function () {
                    // Hanya picu jika nilai cocok persis atau memiliki substring
                    updateDependsOn();
                });
            }

            // Status awal
            updateDependsOn();
        });
    }

    // ======== Dropdown Bergantung (Cascading) ========
    function initDependentDropdowns($modal) {
        $modal.find('.gc-dependent-select').each(function () {
            var $childSelect = $(this);
            var field = $childSelect.data('dependentField');
            var dependsOnField = $childSelect.data('dependsOnField');

            if (!field || !dependsOnField) return;

            var $form = $childSelect.closest('.gc-form');
            if (!$form.length) return;

            var $parentSelect = $form.find('[name="' + dependsOnField + '"]');
            if (!$parentSelect.length) return;

            function loadDependentOptions(parentValue) {
                if (!parentValue || parentValue === '') {
                    $childSelect.find('option:not(:first)').remove();
                    return;
                }

                var currentVal = $childSelect.val();

                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        gc_action: 'dependent_options',
                        field: field,
                        parent_value: parentValue
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success && response.options) {
                            $childSelect.find('option:not(:first)').remove();
                            $.each(response.options, function (i, opt) {
                                var selected = (String(opt.id) === String(currentVal)) ? ' selected' : '';
                                $childSelect.append('<option value="' + opt.id + '"' + selected + '>' + opt.title + '</option>');
                            });
                        }
                    }
                });
            }

            // Dengarkan perubahan pada induk
            $parentSelect.on('change.dependentDropdown', function () {
                loadDependentOptions($(this).val());
            });

            // Muatan awal (mode edit: isi berdasarkan nilai induk saat ini)
            var initialParentValue = $parentSelect.val();
            if (initialParentValue) {
                loadDependentOptions(initialParentValue);
            }
        });
    }

    // Bersihkan listener dependsOn + dependentDropdown saat modal ditutup
    $(document).on('hidden.bs.modal', '.gc-modal', function () {
        $(this).find('[data-depends-on]').each(function () {
            var config = $(this).data('dependsOn');
            if (!config) return;
            var $form = $(this).closest('.gc-form');
            if ($form.length) {
                $form.find('[name="' + config.field + '"]').off('.dependsOn');
            }
        });
        $(this).find('.gc-dependent-select').each(function () {
            var dependsOnField = $(this).data('dependsOnField');
            if (!dependsOnField) return;
            var $form = $(this).closest('.gc-form');
            if ($form.length) {
                $form.find('[name="' + dependsOnField + '"]').off('.dependentDropdown');
            }
        });
    });

    function bindFormEvents($modal) {
        // Pengiriman formulir
        $modal.on('submit', '.gc-form', function (e) {
            e.preventDefault();
            submitForm($(this));
        });

        // Tombol tutup
        $modal.on('click', '.gc-form-close', function (e) {
            e.preventDefault();
            GcModal.hide();
        });

        // Tutup saat backdrop diklik — juga lepaskan kunci catatan
        $modal.on('hidden.bs.modal', function () {
            if (_lockedRecordId) {
                $.post(window.location.href, {
                    gc_action: 'release_lock',
                    id: _lockedRecordId
                });
                _lockedRecordId = null;
            }
            GcModal.remove();
        });

        // Inisialisasi dependsOn setelah formulir ditampilkan
        initDependsOn($modal);

        // Inisialisasi dropdown bergantung (cascading)
        initDependentDropdowns($modal);

        // Inisialisasi tab Bootstrap 5 untuk grup field
        $modal.find('[data-bs-toggle="tab"]').each(function () {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                new bootstrap.Tab(this);
            }
        });

        // Inisialisasi editor teks kaya
        initRichtextEditors($modal);
    }

    function initRichtextEditors($modal) {
        if (typeof Quill === 'undefined') return;

        $modal.find('.gc-richtext-editor').each(function () {
            // Lewati jika sudah diinisialisasi (Quill menambahkan kelas ql-container)
            if ($(this).hasClass('ql-container')) return;

            var quill = new Quill(this, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'header': [1, 2, 3, false] }],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'align': [] }],
                        ['link', 'clean']
                    ]
                }
            });
        });
    }

    function syncRichtextEditors($modal) {
        $modal.find('.gc-richtext-editor').each(function () {
            var quill = Quill.find(this);
            if (quill) {
                var $editor = $(this);
                var editorId = $editor.attr('id');
                var fieldId = editorId.replace('_editor', '');
                $modal.find('#' + fieldId).val(quill.root.innerHTML);
            }
        });
    }

    // ======== Pembantu Debounce ========
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

    // ======== Pengeditan Inline ========
    /**
     * Inisialisasi pengeditan inline pada wrapper.
     */
    function initInlineEditing($wrapper) {
        if (!$wrapper.find('[data-inline-edit]').length) {
            return;
        }

        // Tutup editor aktif yang ada
        function closeActiveEditor() {
            var $active = $('.gc-inline-editor-active');
            if ($active.length) {
                $active.removeClass('gc-inline-editor-active');
                var $cell = $active.closest('td');
                $cell.find('.gc-inline-editor').remove();
                $cell.css('padding', '');
            }
        }

        // Simpan edit inline via AJAX
        function saveInlineEdit($cell, value) {
            var $wrapper = $cell.closest('.grocery-crud-wrapper');
            var id = $cell.closest('tr').data('parent-id');
            if (id === undefined) {
                // Fallback: coba temukan PK di baris
                var pk = $wrapper.data('primaryKey') || 'id';
                id = $cell.closest('tr').find('[data-column="' + pk + '"]').text().trim();
            }
            var field = $cell.data('column');

            if (!id || !field) return;

            // Ambil nilai asli untuk berjaga-jaga jika perlu dikembalikan
            var origValue = $cell.data('value') !== undefined ? $cell.data('value') : $cell.text().trim();

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    gc_action: 'inline_save',
                    id: id,
                    field: field,
                    value: value
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Perbarui sel dengan nilai tampilan yang dikembalikan
                        $cell.html(response.value);
                        $cell.data('value', value);
                        showAlert(response.message, 'success');
                    } else {
                        // Kembalikan ke aslinya
                        var dispValue = $cell.data('value') !== undefined
                            ? ($cell.data('value') === origValue ? origValue : $cell.data('value'))
                            : origValue;
                        $cell.html(dispValue);
                        showAlert(response.message || 'Save failed.', 'danger');
                    }
                },
                error: function () {
                    // Kembalikan saat error
                    var dispValue = $cell.data('value') !== undefined
                        ? ($cell.data('value') === origValue ? origValue : $cell.data('value'))
                        : origValue;
                    $cell.html(dispValue);
                    showAlert('An error occurred while saving.', 'danger');
                }
            });
        }

        // Buat elemen editor inline
        function createInlineEditor($cell, fieldType, currentValue, fieldOptions) {
            var $editor;

            switch (fieldType) {
                case 'select':
                    $editor = $('<select class="form-select form-select-sm gc-inline-select"></select>');
                    if (fieldOptions) {
                        try {
                            var options = typeof fieldOptions === 'string' ? JSON.parse(fieldOptions) : fieldOptions;
                            $.each(options, function (key, label) {
                                var $opt = $('<option></option>').attr('value', key).text(label);
                                if (String(key) === String(currentValue)) {
                                    $opt.prop('selected', true);
                                }
                                $editor.append($opt);
                            });
                        } catch (e) {
                            // Fallback ke input teks
                            $editor = $('<input type="text" class="form-control form-control-sm gc-inline-input">');
                        }
                    } else {
                        $editor = $('<input type="text" class="form-control form-control-sm gc-inline-input">');
                    }
                    break;

                case 'boolean':
                    $editor = $('<div class="form-check form-switch gc-inline-switch d-inline-block"></div>');
                    var $cb = $('<input type="checkbox" class="form-check-input" role="switch">')
                        .prop('checked', currentValue === '1' || currentValue === 1 || currentValue === 'true' || currentValue === true);
                    $editor.append($cb);
                    break;

                case 'number':
                    $editor = $('<input type="number" step="any" class="form-control form-control-sm gc-inline-input">');
                    break;

                case 'date':
                    $editor = $('<input type="date" class="form-control form-control-sm gc-inline-input">');
                    break;

                case 'datetime':
                    $editor = $('<input type="datetime-local" class="form-control form-control-sm gc-inline-input">');
                    break;

                case 'time':
                    $editor = $('<input type="time" class="form-control form-control-sm gc-inline-input">');
                    break;

                case 'email':
                    $editor = $('<input type="email" class="form-control form-control-sm gc-inline-input">');
                    break;

                case 'url':
                    $editor = $('<input type="url" class="form-control form-control-sm gc-inline-input">');
                    break;

                case 'tel':
                    $editor = $('<input type="tel" class="form-control form-control-sm gc-inline-input">');
                    break;

                case 'textarea':
                    $editor = $('<textarea class="form-control form-control-sm gc-inline-textarea" rows="2"></textarea>');
                    break;

                default: // text
                    $editor = $('<input type="text" class="form-control form-control-sm gc-inline-input">');
                    break;
            }

            // Atur nilai
            if ($editor.is('input') || $editor.is('textarea')) {
                $editor.val(currentValue);
            }

            return $editor;
        }

        // Masuk mode edit
        function enterEditMode($cell) {
            // Jangan edit jika sedang diedit
            if ($cell.find('.gc-inline-editor').length) return;
            // Jangan edit kolom aksi
            if ($cell.hasClass('text-center')) return;

            closeActiveEditor();

            var fieldType = $cell.data('inline-edit');
            var currentValue = $cell.data('value') !== undefined ? $cell.data('value') : '';
            var fieldOptions = $cell.data('field-options');

            // Simpan teks tampilan saat ini untuk pembatalan
            $cell.data('orig-display', $cell.html());

            // Kosongkan sel dan buat editor
            var $editor = createInlineEditor($cell, fieldType, currentValue, fieldOptions);
            var $editorWrap = $('<div class="gc-inline-editor"></div>').append($editor);
            $cell.addClass('gc-inline-editor-active');
            $cell.empty().append($editorWrap);

            // Fokus dan pilih
            if ($editor.is('input') && !$editor.is('[type="checkbox"]')) {
                $editor.focus().select();
            } else if ($editor.is('select') || $editor.is('textarea')) {
                $editor.focus();
            }

            // Tangani penyimpanan
            function doSave() {
                var val;
                if ($editor.is('select')) {
                    val = $editor.val();
                } else if ($editor.is('[type="checkbox"]')) {
                    val = $editor.prop('checked') ? '1' : '0';
                } else {
                    val = $editor.val();
                }
                saveInlineEdit($cell, val);
            }

            // Tangani pembatalan
            function doCancel() {
                var origDisplay = $cell.data('orig-display') || '';
                $cell.removeClass('gc-inline-editor-active');
                $cell.html(origDisplay);
            }

            // Simpan saat blur (dengan penundaan untuk memungkinkan klik pada opsi select)
            var blurTimer = null;
            $editor.on('blur', function () {
                blurTimer = setTimeout(function () {
                    doSave();
                }, 200);
            });

            // Batalkan timer blur saat fokus
            $editor.on('focus', function () {
                if (blurTimer) {
                    clearTimeout(blurTimer);
                    blurTimer = null;
                }
            });

            // Simpan saat Enter (bukan untuk textarea)
            $editor.on('keydown', function (e) {
                if (e.keyCode === 13 && !$editor.is('textarea')) {
                    e.preventDefault();
                    if (blurTimer) {
                        clearTimeout(blurTimer);
                        blurTimer = null;
                    }
                    doSave();
                }
                // Batalkan saat Escape
                if (e.keyCode === 27) {
                    e.preventDefault();
                    if (blurTimer) {
                        clearTimeout(blurTimer);
                        blurTimer = null;
                    }
                    doCancel();
                }
            });

            // Untuk select, simpan saat change
            if ($editor.is('select')) {
                $editor.on('change', function () {
                    doSave();
                });
            }

            // Untuk checkbox/switch, simpan saat change
            if ($editor.is('[type="checkbox"]')) {
                $editor.on('change', function () {
                    doSave();
                });
            }
        }

        // Tangani klik dua kali pada sel yang dapat diedit
        $wrapper.off('dblclick', '[data-inline-edit]').on('dblclick', '[data-inline-edit]', function (e) {
            e.preventDefault();
            enterEditMode($(this));
        });
    }

    // ======== Inisialisasi ========
    // ======== Polifil Bootstrap untuk tema non-Bootstrap ========
    function bootstrapPolyfill() {
        var bootstrapLoaded = typeof bootstrap !== 'undefined' && typeof bootstrap.Dropdown === 'function';

        if (!bootstrapLoaded) {
            // Polifil dropdown — tangani dropdown gaya Bootstrap untuk tema non-Bootstrap.
            // Materialize menggunakan sistem dropdown sendiri (.dropdown-trigger), sehingga dikecualikan.
            if (typeof M === 'undefined') {
                $(document).on('click.dropdown', '[data-bs-toggle="dropdown"]', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $btn = $(this);
                    var $wrapper = $btn.closest('.dropdown, .relative');
                    var $menu = $wrapper.find('.dropdown-menu, .dropdown-content, .gc-columns-menu, .gc-settings-menu');
                    if ($menu.length === 0) {
                        $menu = $btn.next('.dropdown-menu, .dropdown-content, ul');
                    }

                    // Tutup semua dropdown lainnya
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

                // Tutup dropdown saat klik di luar
                $(document).on('click.dropdown', function (e) {
                    if (!$(e.target).closest('[data-bs-toggle="dropdown"]').length
                        && !$(e.target).closest('.dropdown-menu, .dropdown-content').length) {
                        $('.dropdown-menu, .dropdown-content, .gc-columns-menu, .gc-settings-menu').hide();
                    }
                });
            }
        }

        // Polifil modal — selalu definisikan (Bootstrap 5 bebas jQuery, tidak mendefinisikan $.fn.modal)
        if (typeof $.fn.modal !== 'function') {
            $.fn.modal = function (action) {
                if (action === 'show') {
                    return this.each(function () {
                        $(this).addClass('show').css('display', 'block');
                        $('body').addClass('modal-open');
                        // Latar belakang
                        if ($('.modal-backdrop').length === 0) {
                            $('body').append('<div class="modal-backdrop" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:1050;background:rgba(0,0,0,0.28)"></div>');
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
        }

        // Polifil alert
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

    // ======== Popover Relasi (tooltip hover untuk relasi) ========
    function initRelationPopovers() {
        var popoverTimeout = null;
        var $popoverEl = null;

        function showPopover($el) {
            var field = $el.data('gc-popover-field');
            var id = $el.data('gc-popover-id');
            if (!field || !id) return;

            // Hapus popover yang ada
            $('.gc-popover-tip').remove();

            $popoverEl = $('<div class="gc-popover-tip shadow" style="position:fixed;z-index:9999;background:#fff;border:1px solid #dee2e6;border-radius:6px;padding:12px;max-width:360px;font-size:13px;display:none;line-height:1.5;"><div class="text-center text-muted py-2"><small>Loading...</small></div></div>');
            $('body').append($popoverEl);

            // Posisikan di bawah elemen
            var offset = $el.offset();
            var elHeight = $el.outerHeight();
            $popoverEl.css({
                left: Math.max(10, offset.left),
                top: offset.top + elHeight + 6
            });
            $popoverEl.fadeIn(150);

            // Ambil konten via AJAX
            $.ajax({
                url: window.location.href.split('?')[0],
                method: 'POST',
                data: {
                    gc_action: 'relation_popover',
                    field: field,
                    id: id
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.html) {
                        $popoverEl.html(response.html);
                    } else {
                        $popoverEl.html('<div class="text-muted text-center py-1"><small>No data available</small></div>');
                    }
                },
                error: function () {
                    $popoverEl.html('<div class="text-muted text-center py-1"><small>Error loading data</small></div>');
                }
            });
        }

        function hidePopover(delay) {
            if (popoverTimeout) clearTimeout(popoverTimeout);
            popoverTimeout = setTimeout(function () {
                $('.gc-popover-tip').fadeOut(100, function () { $(this).remove(); });
            }, delay || 200);
        }

        // Hover pada sel relasi -> tampilkan popover
        $(document).on('mouseenter', '[data-gc-popover-field]', function () {
            if (popoverTimeout) clearTimeout(popoverTimeout);
            showPopover($(this));
        });

        $(document).on('mouseleave', '[data-gc-popover-field]', function () {
            hidePopover(300);
        });

        // Pertahankan popover tetap terlihat saat di-hover
        $(document).on('mouseenter', '.gc-popover-tip', function () {
            if (popoverTimeout) clearTimeout(popoverTimeout);
        });

        $(document).on('mouseleave', '.gc-popover-tip', function () {
            hidePopover(200);
        });
    }

    // ======== Fungsi Penampil Log Aktivitas ========
    function getActivityLogFilters($viewer) {
        return {
            table_name: $viewer.find('.gc-alf-table').val(),
            action: $viewer.find('.gc-alf-action').val(),
            date_from: $viewer.find('.gc-alf-date-from').val(),
            date_to: $viewer.find('.gc-alf-date-to').val()
        };
    }

    function loadActivityLogData($viewer, extraParams) {
        var filters = getActivityLogFilters($viewer);
        var params = $.extend({
            gc_action: 'activity_log_data',
            page: 1,
            perPage: 50,
            sort_field: $viewer.data('sort-field') || 'created_at',
            sort_dir: $viewer.data('sort-dir') || 'DESC'
        }, filters, extraParams);

        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: params,
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success && response.html) {
                    $viewer.find('.activity-log-table-wrapper').html(response.html);
                } else {
                    showAlert(response.message || 'Failed to load log data.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred while loading log data.', 'danger');
            }
        });
    }

    function loadActivityLogViewer($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        if (!$wrapper.length) return;
        if ($wrapper.find('.activity-log-viewer').length) return;

        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { gc_action: 'activity_log_viewer' },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success && response.html) {
                    $wrapper.find('.card').first().hide();
                    $wrapper.append(response.html);
                } else {
                    showAlert(response.message || 'Failed to load activity logs.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred while loading activity logs.', 'danger');
            }
        });
    }

    function goBackToCrudList($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        $wrapper.find('.activity-log-viewer').remove();
        $wrapper.find('.card').first().show();
    }

    function applyActivityLogFilter($btn) {
        var $viewer = $btn.closest('.activity-log-viewer');
        if (!$viewer.length) return;
        loadActivityLogData($viewer, { page: 1 });
    }

    function loadActivityLogPage($link, page) {
        var $viewer = $link.closest('.activity-log-viewer');
        if (!$viewer.length) return;
        loadActivityLogData($viewer, { page: page });
    }

    function sortActivityLog($th) {
        var $viewer = $th.closest('.activity-log-viewer');
        if (!$viewer.length) return;
        $viewer.data('sort-field', $th.data('sort-field') || 'created_at');
        $viewer.data('sort-dir', $th.data('sort-dir') || 'DESC');
        loadActivityLogData($viewer, { page: 1 });
    }

    function showActivityLogDetail($btn, logId) {
        showLoading();
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                gc_action: 'activity_log_detail',
                log_id: logId
            },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success && response.html) {
                    GcModal.show(response.html);
                } else {
                    showAlert(response.message || 'Failed to load log detail.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred while loading log detail.', 'danger');
            }
        });
    }

    // ======== Fungsi Tampilan Kalender ========
    var _gcCalendar = null; // Instance FullCalendar

    function loadCalendarView($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        var $tableContainer = $wrapper.find('.table-responsive');
        var $calendarContainer = $wrapper.find('.gc-calendar-container');
        var calendarEl = document.getElementById($calendarContainer.find('.gc-calendar').attr('id'));

        if (!calendarEl) return;

        // Sembunyikan tabel, tampilkan kalender
        $tableContainer.hide();
        $calendarContainer.show();

        showLoading();

        // Ambil event via AJAX
        $.ajax({
            url: window.location.href,
            method: 'GET',
            data: { gc_action: 'calendar_data' },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (!response.success) {
                    showAlert(response.message || 'Failed to load calendar data.', 'danger');
                    return;
                }

                var events = response.events || [];

                // Hancurkan instance sebelumnya jika ada
                if (_gcCalendar) {
                    _gcCalendar.destroy();
                    _gcCalendar = null;
                }

                // Inisialisasi FullCalendar
                _gcCalendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,dayGridWeek,dayGridDay'
                    },
                    height: 'auto',
                    events: events,
                    eventClick: function (info) {
                        // Buka formulir edit saat mengklik event
                        var id = info.event.id;
                        if (id) {
                            var $editBtn = $wrapper.find('[data-action="edit"][data-id="' + id + '"]');
                            if ($editBtn.length) {
                                $editBtn.trigger('click');
                            }
                        }
                        info.jsEvent.preventDefault();
                    },
                    noEventsText: 'No events found.',
                    loading: function (isLoading) {
                        if (isLoading) showLoading();
                        else hideLoading();
                    }
                });

                _gcCalendar.render();
            },
            error: function () {
                hideLoading();
                showAlert('Failed to load calendar data.', 'danger');
            }
        });
    }

    function goBackToTableView($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        var $tableContainer = $wrapper.find('.table-responsive');
        var $calendarContainer = $wrapper.find('.gc-calendar-container');

        // Hancurkan instance FullCalendar
        if (_gcCalendar) {
            _gcCalendar.destroy();
            _gcCalendar = null;
        }

        $calendarContainer.hide();
        $tableContainer.show();
    }

    // ======== File Manager Helpers ========

    /**
     * Memuat panel File Manager.
     */
    function loadFileManager($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        showLoading();

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                gc_action: 'file_manager',
                path: '',
                view: 'list'
            },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success && response.html) {
                    $wrapper.find('.gc-list-content').html(response.html);
                    $wrapper.find('.gc-list-content').data('fm-path', '');
                } else {
                    showAlert(response.message || 'Failed to load file manager.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred while loading file manager.', 'danger');
            }
        });
    }

    /**
     * Navigasi ke folder di file manager.
     */
    function fmNavigateTo($container, path) {
        var $wrapper = $container.closest('.grocery-crud-wrapper');
        showLoading();

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                gc_action: 'file_manager_list',
                path: path,
                view: $container.data('fm-view') || 'list'
            },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success) {
                    $container.find('.gc-fm-list-container').html(response.html);
                    $container.data('fm-path', path);
                    // Update status bar
                    $container.find('.gc-fm-status-path strong').text(path || '/');
                } else {
                    showAlert(response.message || 'Failed to load directory.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred.', 'danger');
            }
        });
    }

    /**
     * Muat ulang daftar file di direktori saat ini.
     */
    function fmRefreshList($container) {
        var path = $container.data('fm-path') || '';
        fmNavigateTo($container, path);
    }

    /**
     * Upload file.
     */
    function fmUpload($container, files) {
        var $wrapper = $container.closest('.grocery-crud-wrapper');
        var path = $container.data('fm-path') || '';
        var formData = new FormData();
        formData.append('gc_action', 'file_manager_upload');
        formData.append('path', path);

        for (var i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        showLoading();

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message || 'Upload successful.', 'success');
                    fmRefreshList($container);
                } else {
                    showAlert(response.message || 'Upload failed.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('Upload failed.', 'danger');
            }
        });
    }

    /**
     * Buat folder baru.
     */
    function fmCreateFolder($container) {
        var $wrapper = $container.closest('.grocery-crud-wrapper');
        var path = $container.data('fm-path') || '';
        var folderName = prompt(
            $container.find('.gc-fm-new-folder').data('prompt') || 'Enter folder name:'
        );

        if (!folderName || folderName.trim() === '') return;

        showLoading();

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                gc_action: 'file_manager_create_folder',
                path: path,
                name: folderName.trim()
            },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message || 'Folder created.', 'success');
                    fmRefreshList($container);
                    fmRefreshTree($container);
                } else {
                    showAlert(response.message || 'Failed to create folder.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred.', 'danger');
            }
        });
    }

    /**
     * Ganti nama file/folder.
     */
    function fmRename($container, path, currentName) {
        var newName = prompt('Rename to:', currentName);
        if (!newName || newName.trim() === '' || newName.trim() === currentName) return;

        showLoading();

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                gc_action: 'file_manager_rename',
                path: path,
                name: newName.trim()
            },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message || 'Renamed successfully.', 'success');
                    fmRefreshList($container);
                    fmRefreshTree($container);
                } else {
                    showAlert(response.message || 'Failed to rename.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred.', 'danger');
            }
        });
    }

    /**
     * Hapus file/folder dengan konfirmasi.
     */
    function fmDelete($container, path, name, isDir) {
        var msg = 'Are you sure you want to delete "' + name + '"?';
        if (isDir) {
            msg += ' All contents inside will also be deleted.';
        }
        if (!confirm(msg)) return;

        showLoading();

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                gc_action: 'file_manager_delete',
                path: path
            },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message || 'Deleted successfully.', 'success');
                    fmRefreshList($container);
                    fmRefreshTree($container);
                } else {
                    showAlert(response.message || 'Failed to delete.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred.', 'danger');
            }
        });
    }

    /**
     * Search files.
     */
    function fmSearch($container, query) {
        var $wrapper = $container.closest('.grocery-crud-wrapper');
        var path = $container.data('fm-path') || '';

        showLoading();

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                gc_action: 'file_manager_search',
                query: query,
                path: path
            },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success) {
                    var results = response.results || [];
                    var html = '';

                    if (results.length === 0) {
                        html = '<div class="text-center text-muted py-5"><i class="bi bi-search d-block mb-2" style="font-size:2.5rem;opacity:0.3;"></i>No files found.</div>';
                    } else {
                        results.forEach(function (item) {
                            html += '<div class="gc-fm-item d-flex align-items-center px-3 py-2 border-bottom" data-path="' + item.path + '" data-name="' + item.name + '" data-isdir="' + (item.isDir ? '1' : '0') + '">';
                            html += '<div class="gc-fm-col-icon text-center me-3 ' + (item.isDir ? 'text-warning' : 'text-secondary') + '" style="width:24px;"><i class="bi ' + (item.icon || 'bi-file-earmark') + '"></i></div>';
                            html += '<div class="flex-grow-1 text-truncate"><span class="text-body">' + item.name + '</span></div>';
                            html += '<div class="text-muted small text-end text-nowrap" style="min-width:80px;">' + (item.sizeHuman || '-') + '</div>';
                            html += '<div class="text-muted small text-end text-nowrap" style="min-width:150px;">' + (item.modified || '-') + '</div>';
                            html += '<div style="min-width:100px;"></div>';
                            html += '</div>';
                        });
                    }

                    $container.find('.gc-fm-list-container').html(html);
                } else {
                    showAlert(response.message || 'Search failed.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred during search.', 'danger');
            }
        });
    }

    /**
     * Memuat ulang folder tree di sidebar.
     */
    function fmRefreshTree($container) {
        var $wrapper = $container.closest('.grocery-crud-wrapper');

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: { gc_action: 'file_manager_tree' },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.html) {
                    $container.find('.gc-fm-tree').html(response.html);
                }
            }
        });
    }

    $(document).ready(function () {
        bootstrapPolyfill();
        bindEvents();
        initRelationPopovers();

        // Muat otomatis visibilitas kolom yang disimpan dari localStorage
        $('.grocery-crud-wrapper').each(function () {
            var $wrapper = $(this);
            var url = window.location.href;
            try {
                var raw = localStorage.getItem('gc_settings_' + btoa(url));
                console.log('[GC_AUTO] url=', url, 'has_raw=', !!raw, 'menu_len=', $wrapper.find('.gc-columns-menu .form-check').length);
                if (raw) {
                    var settings = JSON.parse(raw);
                    console.log('[GC_AUTO] settings=', settings);
                    if (settings.columnOrder && settings.columnOrder.length) {
                        console.log('[GC_AUTO] applyColumnOrder', settings.columnOrder);
                        applyColumnOrder($wrapper, settings.columnOrder);
                        console.log('[GC_AUTO] after applyColumnOrder');
                    }
                    if (settings.columns) {
                        $wrapper.find('.gc-columns-menu input[type="checkbox"]').each(function () {
                            var col = $(this).data('column');
                            if (settings.columns[col] !== undefined) {
                                console.log('[GC_AUTO] toggling col', col, 'to', settings.columns[col]);
                                $(this).prop('checked', settings.columns[col]).trigger('change');
                            }
                        });
                    }
                }
            } catch (e) {
                console.log('[GC_AUTO] error', e);
            }
            // Inisialisasi table-dragger setelah pengaturan dipulihkan
            initTableDragger($wrapper);
        });

        // Simpan pesan konfirmasi
        $('.grocery-crud-wrapper').each(function () {
            var $wrapper = $(this);
            var deleteMsg = $wrapper.find('[data-confirm-delete]').data('confirm-delete');
            if (deleteMsg) {
                $wrapper.data('confirm-delete', deleteMsg);
            }
        });

    // ======== File Manager Event Handlers ========

    // Klik item folder di daftar
    $(document).on('click', '.gc-fm-item-folder.gc-fm-item-clickable', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        var path = $(this).data('path');
        if (path !== undefined) {
            fmNavigateTo($container, path);
        }
    });

    // Klik link folder
    $(document).on('click', '.gc-fm-folder-link', function (e) {
        e.preventDefault();
        var $item = $(this).closest('.gc-fm-item-folder');
        var $container = $(this).closest('.gc-fm-content');
        var path = $item.data('path');
        if (path !== undefined) {
            fmNavigateTo($container, path);
        }
    });

    // Klik item parent (..)
    $(document).on('click', '.gc-fm-item-parent', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        var path = $(this).data('path');
        if (path !== undefined) {
            fmNavigateTo($container, path);
        }
    });

    // Klik breadcrumb
    $(document).on('click', '.gc-fm-breadcrumb-link', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        var path = $(this).data('path');
        fmNavigateTo($container, path);
    });

    // Klik folder tree (sidebar)
    $(document).on('click', '.gc-fm-tree-item > a', function (e) {
        e.preventDefault();
        var $item = $(this).parent();
        var $container = $(this).closest('.gc-fm-content');
        var path = $item.data('path');
        if (path !== undefined) {
            fmNavigateTo($container, path);
        }
    });

    // Tree toggle expand
    $(document).on('click', '.gc-fm-tree-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $icon = $(this);
        var $childList = $icon.closest('li').find('> .gc-fm-tree-children');
        if ($childList.length) {
            $childList.toggle();
            $icon.toggleClass('bi-chevron-right bi-chevron-down');
        }
    });

    // Tombol refresh
    $(document).on('click', '.gc-fm-refresh', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        fmRefreshList($container);
    });

    // Refresh tree
    $(document).on('click', '.gc-fm-refresh-tree', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        fmRefreshTree($container);
    });

    // Tombol folder baru
    $(document).on('click', '.gc-fm-new-folder', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        fmCreateFolder($container);
    });

    // Upload button -> trigger file input
    $(document).on('click', '.gc-fm-upload-btn', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        $container.find('.gc-fm-upload-input').click();
    });

    // Upload input change -> upload
    $(document).on('change', '.gc-fm-upload-input', function () {
        var $container = $(this).closest('.gc-fm-content');
        var files = this.files;
        if (files && files.length > 0) {
            fmUpload($container, files);
        }
        this.value = ''; // reset supaya file yang sama bisa dipilih ulang
    });

    // Search
    $(document).on('click', '.gc-fm-search-btn', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        var $input = $container.find('.gc-fm-search');
        var query = $input.val().trim();
        if (query) {
            fmSearch($container, query);
            $container.find('.gc-fm-search-clear').show();
        }
    });

    // Search on enter
    $(document).on('keydown', '.gc-fm-search', function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            $(this).closest('.gc-fm-content').find('.gc-fm-search-btn').click();
        }
    });

    // Clear search
    $(document).on('click', '.gc-fm-search-clear', function () {
        var $container = $(this).closest('.gc-fm-content');
        $container.find('.gc-fm-search').val('');
        $(this).hide();
        fmRefreshList($container);
    });

    // Action: Rename
    $(document).on('click', '.gc-fm-action-rename', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        var $item = $(this).closest('.gc-fm-item');
        var path = $item.data('path');
        var name = $item.data('name');
        fmRename($container, path, name);
    });

    // Action: Delete
    $(document).on('click', '.gc-fm-action-delete', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        var $item = $(this).closest('.gc-fm-item');
        var path = $item.data('path');
        var name = $item.data('name');
        var isDir = $item.data('isdir') === '1';
        fmDelete($container, path, name, isDir);
    });

    // Action: Move - pilih tujuan via prompt
    $(document).on('click', '.gc-fm-action-move', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        var $item = $(this).closest('.gc-fm-item');
        var source = $item.data('path');
        var name = $item.data('name');
        var dest = prompt('Destination path (relative to upload root):', '');
        if (dest === null) return;

        showLoading();

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                gc_action: 'file_manager_move',
                source: source,
                destination: dest
            },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message || 'Moved successfully.', 'success');
                    fmRefreshList($container);
                    fmRefreshTree($container);
                } else {
                    showAlert(response.message || 'Failed to move.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred.', 'danger');
            }
        });
    });

    // Action: Copy - pilih tujuan via prompt
    $(document).on('click', '.gc-fm-action-copy', function (e) {
        e.preventDefault();
        var $container = $(this).closest('.gc-fm-content');
        var $item = $(this).closest('.gc-fm-item');
        var source = $item.data('path');
        var name = $item.data('name');
        var dest = prompt('Destination path (relative to upload root):', '');
        if (dest === null) return;

        showLoading();

        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                gc_action: 'file_manager_copy',
                source: source,
                destination: dest
            },
            dataType: 'json',
            success: function (response) {
                hideLoading();
                if (response.success) {
                    showAlert(response.message || 'Copied successfully.', 'success');
                    fmRefreshList($container);
                    fmRefreshTree($container);
                } else {
                    showAlert(response.message || 'Failed to copy.', 'danger');
                }
            },
            error: function () {
                hideLoading();
                showAlert('An error occurred.', 'danger');
            }
        });
    });

    // Action: Preview gambar (modal)
    $(document).on('click', '.gc-fm-action-preview', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        if (!url) return;

        var modalHtml = '<div class="modal fade" tabindex="-1">'
            + '<div class="modal-dialog modal-lg modal-dialog-centered">'
            + '<div class="modal-content">'
            + '<div class="modal-header border-0 pb-0">'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>'
            + '</div>'
            + '<div class="modal-body text-center">'
            + '<img src="' + url + '" alt="" class="img-fluid">'
            + '</div>'
            + '</div></div></div>';

        var $modal = $(modalHtml);
        $('body').append($modal);
        $modal.modal('show');
        $modal.on('hidden.bs.modal', function () {
            $modal.remove();
        });
    });

    // File Manager back to CRUD list
    $(document).on('click', '.gc-fm-back-to-list', function (e) {
        e.preventDefault();
        var $wrapper = $(this).closest('.grocery-crud-wrapper');
        refreshList($wrapper);
    });

    // ======== Aksi Pratinjau (btn-preview) ========
        $(document).on('click', '.btn-preview', function (e) {
            e.preventDefault();
            var $wrapper = $(this).closest('.grocery-crud-wrapper');
            var recordId = $(this).data('id');
            var $row = $wrapper.find('tr[data-parent-id="' + recordId + '"]');
            if (!$row.length) return;

            var html = '<div class="p-3">';
            html += '<h5 class="mb-3 fw-bold"><i class="bi bi-eye me-2"></i>Record Preview</h5>';
            html += '<table class="table table-bordered table-sm">';
            $row.find('td[data-column]').each(function () {
                var col = $(this).data('column');
                var label = $wrapper.find('th[data-column="' + col + '"]').data('label') || col;
                var val = $(this).html();
                html += '<tr><th style="width:30%;background:#f8f9fa;">' + label + '</th><td>' + val + '</td></tr>';
            });
            html += '</table></div>';

            GcModal.show(html);
        });
    });

})(jQuery);
