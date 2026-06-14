/**
 * Grocery CRUD - File Manager helper.
 * Handler upload, navigasi folder, dan pemilih file untuk field setUpload().
 */
(function ($) {
    'use strict';
    if (!$) return;

    function gcUrl() { return window.location.href.split('#')[0]; }
    function esc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }

    function notify(message, type) {
        type = type || 'success';
        var $el = $('<div class="gc-alert alert alert-' + type + ' alert-dismissible fade show shadow" role="alert" style="position:fixed;top:16px;right:16px;z-index:20000;max-width:420px">'
            + esc(message) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
        $('body').append($el);
        setTimeout(function () { $el.fadeOut(function () { $el.remove(); }); }, 3500);
    }

    function currentPath($fm) {
        var $status = $fm.find('.gc-fm-status-path').first();
        return $status.data('path') || $status.attr('data-path') || '';
    }

    function setPath($fm, path) {
        path = path || '';
        $fm.find('.gc-fm-status-path').first()
            .data('path', path)
            .attr('data-path', path)
            .html('Current directory: <strong>' + esc(path || '/') + '</strong>');

        $fm.find('.gc-fm-tree-item > a').removeClass('active');
        $fm.find('.gc-fm-tree-item').filter(function () {
            return ($(this).data('path') || '') === path;
        }).children('a').first().addClass('active');
    }

    function loadList($fm, path) {
        path = path == null ? currentPath($fm) : path;
        $.ajax({
            url: gcUrl(),
            method: 'POST',
            dataType: 'json',
            data: { gc_action: 'file_manager_list', path: path },
            success: function (res) {
                if (res.success && res.html) {
                    $fm.find('.gc-fm-list-container').html(res.html);
                    setPath($fm, path);
                } else {
                    notify(res.message || 'Gagal memuat File Manager.', 'danger');
                }
            },
            error: function () {
                notify('Terjadi kesalahan saat memuat File Manager.', 'danger');
            }
        });
    }

    function loadTree($fm) {
        $.ajax({
            url: gcUrl(),
            method: 'POST',
            dataType: 'json',
            data: { gc_action: 'file_manager_tree' },
            success: function (res) {
                if (!res.success || !res.html) return;
                var $root = $fm.find('.gc-fm-tree-root').first();
                $root.children('ul.gc-fm-tree-children').remove();
                $root.append(res.html);
                setPath($fm, currentPath($fm));
            }
        });
    }

    function openFileManager($btn) {
        var $wrapper = $btn.closest('.grocery-crud-wrapper');
        if (!$wrapper.length) return;

        $.ajax({
            url: gcUrl(),
            method: 'POST',
            dataType: 'json',
            data: { gc_action: 'file_manager' },
            success: function (res) {
                if (res.success && res.html) {
                    $wrapper.children('.card').first().hide();
                    $wrapper.find('.gc-file-manager').remove();
                    $wrapper.append(res.html);
                } else {
                    notify(res.message || 'Gagal membuka File Manager.', 'danger');
                }
            },
            error: function () {
                notify('Terjadi kesalahan saat membuka File Manager.', 'danger');
            }
        });
    }

    window.loadFileManager = function ($btn) { openFileManager($btn); };

    function openPicker(fieldName, $form) {
        var modalId = 'gc-file-manager-picker-modal';
        $('#' + modalId).remove();

        var $modal = $('<div class="modal fade" id="' + modalId + '" tabindex="-1">'
            + '<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">'
            + '<div class="modal-content"><div class="modal-header">'
            + '<h5 class="modal-title"><i class="bi bi-folder2-open me-2"></i>Pilih File</h5>'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>'
            + '</div><div class="modal-body p-0"><div class="p-4 text-muted">Memuat...</div></div></div></div></div>');

        $('body').append($modal);
        $modal.data('field', fieldName).data('form', $form).modal('show');

        $.ajax({
            url: gcUrl(),
            method: 'POST',
            dataType: 'json',
            data: { gc_action: 'file_manager' },
            success: function (res) {
                if (res.success && res.html) {
                    $modal.find('.modal-body').html(res.html);
                    $modal.find('.gc-file-manager')
                        .attr('data-picker', '1')
                        .attr('data-field', fieldName)
                        .prepend('<div class="alert alert-info m-3 mb-0 small">Klik file untuk memilih. Kamu juga bisa upload file baru dari sini.</div>');
                } else {
                    $modal.find('.modal-body').html('<div class="p-4 text-danger">' + esc(res.message || 'Gagal membuka File Manager.') + '</div>');
                }
            }
        });

        $modal.on('hidden.bs.modal', function () {
            $modal.remove();
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
        });
    }

    function pickFile($item) {
        var $fm = $item.closest('.gc-file-manager');
        if ($fm.attr('data-picker') !== '1') return false;
        if (String($item.attr('data-isdir')) === '1') return false;

        var $modal = $('#gc-file-manager-picker-modal');
        var field = $fm.attr('data-field');
        var $form = $modal.data('form');
        var path = $item.data('path') || $item.attr('data-path') || '';
        var name = $item.data('name') || $item.attr('data-name') || path;
        if (!field || !$form || !$form.length || !path) return true;

        var $file = $form.find('input[type="file"][name="' + field + '"]').first();
        var $hidden = $form.find('input[type="hidden"][name="' + field + '_existing"]').first();
        if (!$hidden.length) {
            $hidden = $('<input type="hidden">').attr('name', field + '_existing').insertAfter($file);
        }

        $hidden.val(path);
        $file.val('');

        var $info = $form.find('.gc-fm-picked-file[data-field="' + field + '"]').first();
        if (!$info.length) {
            $info = $('<div class="gc-fm-picked-file small text-success mt-1" data-field="' + esc(field) + '"></div>')
                .appendTo($file.closest('.mb-3, .form-group, .input-field'));
        }
        $info.html('<i class="bi bi-check-circle me-1"></i>Dipilih dari File Manager: <strong>' + esc(name) + '</strong>');
        $modal.modal('hide');
        return true;
    }

    function enhanceUploadFields(context) {
        $(context || document).find('form.gc-form input[type="file"][name]').each(function () {
            var $input = $(this);
            var field = $input.attr('name');
            if (!field || $input.data('gc-fm-ready')) return;

            $input.data('gc-fm-ready', true);
            $('<button type="button" class="btn btn-outline-secondary btn-sm mt-2 gc-open-file-manager-picker">'
                + '<i class="bi bi-folder2-open me-1"></i>Pilih dari File Manager</button>')
                .attr('data-field', field)
                .insertAfter($input);
        });
    }

    $(document).on('click', '.gc-fm-back-to-list', function (e) {
        e.preventDefault();
        var $modal = $(this).closest('#gc-file-manager-picker-modal');
        if ($modal.length) {
            $modal.modal('hide');
            return;
        }
        var $wrapper = $(this).closest('.grocery-crud-wrapper');
        $wrapper.find('.gc-file-manager').remove();
        $wrapper.children('.card').first().show();
    });

    $(document).on('click', '.gc-fm-upload-btn', function (e) {
        e.preventDefault();
        $(this).closest('.gc-file-manager').find('.gc-fm-upload-input').first().trigger('click');
    });

    $(document).on('change', '.gc-fm-upload-input', function () {
        var files = this.files;
        if (!files || !files.length) return;

        var $input = $(this);
        var $fm = $input.closest('.gc-file-manager');
        var data = new FormData();

        data.append('gc_action', 'file_manager_upload');
        data.append('path', currentPath($fm));
        $.each(files, function (i, file) { data.append('files[]', file); });

        $.ajax({
            url: gcUrl(),
            method: 'POST',
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                notify(res.message || (res.success ? 'Upload berhasil.' : 'Upload gagal.'), res.success ? 'success' : 'danger');
                if (res.success) {
                    loadList($fm, currentPath($fm));
                    loadTree($fm);
                }
            },
            error: function () { notify('Terjadi kesalahan saat upload.', 'danger'); },
            complete: function () { $input.val(''); }
        });
    });

    $(document).on('click', '.gc-fm-item-folder, .gc-fm-folder-link', function (e) {
        if ($(e.target).closest('.dropdown, .dropdown-menu').length) return;
        e.preventDefault();
        var $item = $(this).closest('.gc-fm-item');
        loadList($item.closest('.gc-file-manager'), $item.data('path') || $item.attr('data-path') || '');
    });

    $(document).on('click', '.gc-fm-tree-item > a, .gc-fm-breadcrumb-link', function (e) {
        e.preventDefault();
        var $link = $(this);
        var path = $link.data('path') || $link.closest('.gc-fm-tree-item').data('path') || '';
        loadList($link.closest('.gc-file-manager'), path);
    });

    $(document).on('click', '.gc-fm-refresh, .gc-fm-refresh-tree', function (e) {
        e.preventDefault();
        var $fm = $(this).closest('.gc-file-manager');
        loadList($fm, currentPath($fm));
        loadTree($fm);
    });

    $(document).on('click', '.gc-open-file-manager-picker', function (e) {
        e.preventDefault();
        openPicker($(this).data('field'), $(this).closest('form'));
    });

    $(document).on('click', '.gc-fm-item-file', function (e) {
        if (pickFile($(this))) e.preventDefault();
    });

    $(function () { enhanceUploadFields(document); });
    $(document).ajaxComplete(function () { enhanceUploadFields(document); });
})(window.jQuery);
