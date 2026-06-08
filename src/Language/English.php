<?php

declare(strict_types=1);

namespace GroceryCrud\Language;

class English
{
    public array $strings = [
        'add_record'        => 'Add Record',
        'edit_record'       => 'Edit Record',
        'delete_record'     => 'Delete Record',
        'cancel'            => 'Cancel',
        'save'              => 'Save',
        'add'               => 'Add',
        'edit'              => 'Edit',
        'delete'            => 'Delete',
        'view'              => 'View',
        'search'            => 'Search',
        'reset'             => 'Reset',
        'actions'           => 'Actions',
        'no_records'        => 'No records found.',
        'records'           => 'records',
        'to'                => 'to',
        'of'                => 'of',
        'page'              => 'Page',
        'first'             => 'First',
        'last'              => 'Last',
        'previous'          => 'Previous',
        'next'              => 'Next',
        'per_page'          => 'per page',
        'loading'           => 'Loading...',
        'processing'        => 'Processing...',
        'confirm_delete'    => 'Are you sure you want to delete this record?',
        'confirm_delete_multiple' => 'Are you sure you want to delete the selected records?',
        'delete_success'    => 'Record deleted successfully.',
        'delete_fail'       => 'Failed to delete record.',
        'insert_success'    => 'Record inserted successfully.',
        'insert_fail'       => 'Failed to insert record.',
        'update_success'    => 'Record updated successfully.',
        'update_fail'       => 'Failed to update record.',
        'upload_error'      => 'File upload error.',
        'invalid_file_type' => 'Invalid file type.',
        'file_too_large'    => 'File size exceeds the limit.',
        'required'          => 'This field is required.',
        'unique'            => 'This value already exists.',
        'export'            => 'Export',
        'export_csv'        => 'Export to CSV',
        'export_excel'      => 'Export to Excel',
        'export_pdf'        => 'Export to PDF',
        'print_view'        => 'Print View',
        'filter'            => 'Filter',
        'all'               => 'All',
        'print'             => 'Print',
        'select_all'        => 'Select All',
        'deselect_all'      => 'Deselect All',
        'selected'          => 'selected',
        'no_item_selected'  => 'No item selected.',

        // Soft Delete
        'batch_success'     => 'Batch action completed successfully.',
        'batch_fail'        => 'Batch action failed.',

        // Soft Delete
        'restore'           => 'Restore',
        'restore_success'   => 'Record restored successfully.',
        'restore_fail'      => 'Failed to restore record.',
        'trash_list'        => 'Trash',
        'active_list'       => 'Active Records',

        // RBAC / Permissions
        'permission_denied' => 'You do not have permission to perform this action.',

        // Import
        'import'                => 'Import',
        'import_csv_excel'      => 'Import from CSV / Excel',
        'import_upload'         => 'Upload File',
        'import_upload_hint'    => 'Select a CSV or Excel (.xlsx) file to import.',
        'import_column_mapping' => 'Column Mapping',
        'import_preview'        => 'Preview',
        'import_total_rows'     => 'Total rows in file',
        'import_map_to'         => 'Map to',
        'import_not_mapped'     => 'Not mapped',
        'import_execute'        => 'Import Data',
        'import_success'        => 'Successfully imported {imported} of {total} records.',
        'import_error'          => 'Import failed. {errors} errors occurred.',
        'import_no_data'        => 'No data to import.',
        'import_file_required'  => 'Please select a file to import.',
        'import_confirm'        => 'Are you sure you want to import {total} records?',

        // Activity Log / Audit Trail
        'activity_log'          => 'Activity Log',
        'activity_logs'         => 'Activity Logs',
        'activity_log_empty'    => 'No activities recorded yet.',
        'activity_log_action_insert' => 'Created',
        'activity_log_action_update' => 'Updated',
        'activity_log_action_delete' => 'Deleted',
        'activity_log_action_restore' => 'Restored',
        'activity_log_action_import' => 'Imported',
        'activity_log_table'    => 'Table',
        'activity_log_record'   => 'Record',
        'activity_log_user'     => 'User',
        'activity_log_action'   => 'Action',
        'activity_log_date'     => 'Date',
        'activity_log_ip'       => 'IP Address',
        'activity_log_old_value' => 'Old Value',
        'activity_log_new_value' => 'New Value',
        'activity_log_no_changes' => 'No changes',
        'activity_log_detail'   => 'Detail',

        // Export Selected Columns
        'export_select_columns'     => 'Select Columns to Export',
        'export_selected'           => 'Export Selected',
        'select_columns_hint'       => 'Choose which columns to include in the export.',
        'export_no_column_selected' => 'Please select at least one column.',
    ];
}
