# Grocery CRUD untuk CodeIgniter 4

Library CRUD generator full-featured untuk CodeIgniter 4. Terinspirasi dari Grocery CRUD untuk CI3.

## Fitur

- **CRUD Lengkap** — Create, Read, Update, Delete dengan AJAX, modal form, real-time list refresh
- **Relations** — Belongs_to & Many-to-many (NtoN) dengan display value otomatis
- **Sub-Grid** — Expandable nested tabel relasi di bawah setiap record
- **Soft Delete** — Hapus sementara, restore, trash view, batch restore
- **Batch Actions** — Delete Selected & Restore Selected dengan select-all checkbox
- **Callbacks** — beforeInsert, afterInsert, beforeUpdate, afterUpdate, beforeDelete, afterDelete, callbackColumn, callbackField, callbackAddField, callbackEditField
- **Validation** — Validasi CI4 terintegrasi (required, unique, custom rules)
- **Upload** — File & image upload dengan thumbnail preview + image viewer (click to zoom)
- **Import** — CSV & Excel import dengan auto-column mapping, preview, dan bulk insert
- **Export** — CSV & Excel export
- **Search** — Pencarian real-time dengan debounce
- **Column Filters** — Filter per-kolom (text, dropdown, relation dropdown)
- **Advanced Filters** — Multi-condition filter panel (contains, equals, starts with, dll)
- **Sortable Columns** — Sort asc/desc dengan klik header kolom
- **Column Visibility** — Show/hide kolom dari dropdown menu
- **Settings** — Save/load/reset konfigurasi kolom & filter ke localStorage
- **Theme System** — Bootstrap 5 & AdminLTE 4, mudah ditambahkan tema baru
- **Multi-language** — English & Indonesian bawaan
- **Custom Actions** — Tombol aksi kustom per baris
- **Repeater Fields** — Repeatable group of sub-fields (Nova-style)
- **Dynamic Form Conditions** — Show/hide atau enable/disable field berdasarkan nilai field lain (dependsOn)
- **Field Type Detection** — Auto-detect tipe field dari database
- **Field Type Override** — Override tipe field manual (dropdown, enum, color, dll)
- **Cache Busting** — Version query param otomatis pada CSS/JS assets

## Instalasi

### Via Composer

```bash
composer require triasfahrudin/grocery-crud
```

### Atau dengan repositories path (development)

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../grocery-crud"
        }
    ],
    "require": {
        "triasfahrudin/grocery-crud": "@dev"
    }
}
```

## Quick Start

### 1. Basic CRUD

```php
<?php

namespace App\Controllers;

use GroceryCrud\GroceryCrud;

class Products extends BaseController
{
    public function index()
    {
        $crud = new GroceryCrud();
        $crud->setTable('products');
        $crud->setColumns('name', 'price', 'category');
        $crud->setFields('name', 'price', 'category');
        $crud->displayAs('name', 'Product Name');
        $crud->displayAs('price', 'Price');

        return $crud->render();
    }
}
```

### 2. Dengan Relations

```php
$crud = new GroceryCrud();
$crud->setTable('products');

// Belongs_to: field category_id di tabel products
// mereferensi ke tabel categories, field name
$crud->setRelation('category_id', 'categories', 'name');

// N-to-N: products punya banyak tags melalui product_tags
$crud->setRelationNtoN(
    'tags',           // field name di form
    'product_tags',   // junction table
    'product_id',     // FK ke products
    'tag_id',         // FK ke target table
    'tags',           // target table
    'name'            // title field
);
```

### 3. Dengan Callbacks

```php
$crud->callbackBeforeInsert(function ($data) {
    $data['created_at'] = date('Y-m-d H:i:s');
    return $data;
});

$crud->callbackColumn('price', function ($value, $row) {
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
});

$crud->callbackAfterInsert(function ($data) {
    // log activity, send email, etc.
    return true;
});
```

### 4. Dropdown dengan Opsi Kustom

```php
$crud->setFieldType('is_active', 'dropdown', [
    '1' => 'Active',
    '0' => 'Inactive',
]);
$crud->displayAs('is_active', 'Status');
// Gunakan callbackColumn untuk menampilkan badge di list view
$crud->callbackColumn('is_active', function ($value, $row) {
    return $value == 1
        ? '<span class="badge bg-success">Active</span>'
        : '<span class="badge bg-secondary">Inactive</span>';
});
```

### 5. Dengan Upload & Validation

```php
$crud->setUpload('image', [
    'allowedTypes' => 'jpg|jpeg|png',
    'maxSize'      => 1024,
]);

$crud->required('name');
$crud->unique('email');
$crud->setRules('price', 'numeric|greater_than[0]');
```

### 6. Column Filters

```php
// Filter text
$crud->setColumnFilter('name', 'text');

// Filter dropdown dengan opsi statis
$crud->setColumnFilter('is_active', 'dropdown', ['1' => 'Active', '0' => 'Inactive']);

// Filter dropdown dengan data dari tabel relasi
$crud->setColumnFilterRelation('category_id', 'categories', 'name', 'id', "status = 'active'", 'name ASC');
```

### 7. Batch Actions

```php
// Delete Selected (built-in)
$crud->setBatchAction('delete_selected', 'Delete Selected');

// Restore Selected (built-in, untuk soft delete)
$crud->setBatchAction('restore_selected', 'Restore Selected');
```

### 8. Soft Delete

```php
// Aktifkan soft delete (menyembunyikan record terhapus dari list)
$crud->setSoftDelete();

// Tampilkan trash view (record yang sudah di-soft-delete)
$crud->withTrashed();

// Atau lewat tombol Trash di toolbar (toggle otomatis)
```

### 9. Sub-Grid

```php
$crud->setSubGrid(
    'variants',                      // Identifier field
    'product_variants',              // Related table
    'product_id',                    // FK di related table
    ['name', 'price', 'stock'],      // Columns
    ['name' => 'Variant', 'price' => 'Price', 'stock' => 'Stock'],  // Labels
    []                               // Relations
);
```

### 10. Dynamic Form Conditions (Depends On)

Show/hide atau enable/disable field berdasarkan nilai field lain:

```php
$crud = new GroceryCrud();
$crud->setTable('products');

// Sembunyikan discount_price jika has_discount tidak dicentang
$crud->dependsOn('discount_price', 'has_discount', true);

// Nonaktifkan shipping_address jika same_as_billing dicentang
$crud->dependsOn('shipping_address', 'same_as_billing', true, 'enable');
```

Parameter:
| Parameter | Deskripsi |
|-----------|-----------|
| `$field` | Field yang akan di-show/hide atau enable/disable |
| `$dependsOnField` | Field controller yang memicu perubahan |
| `$value` | Nilai yang memicu aksi (string, angka, boolean) |
| `$action` | `'show'` (default) — sembunyikan field saat tidak cocok; `'enable'` — disable field saat tidak cocok |

Cocok untuk berbagai tipe field: dropdown, switch/boolean, checkbox, text input, dll.

### 11. Repeater Fields

```php
$crud->setRepeater('specs', 'Product Specs', [
    ['name' => 'key',   'label' => 'Specification', 'type' => 'text', 'rules' => 'required|max_length[100]'],
    ['name' => 'value', 'label' => 'Value',          'type' => 'text', 'rules' => 'required|max_length[255]'],
], 'json');
```

### 12. Import CSV/Excel

Upload file CSV atau Excel (.xlsx), auto-map kolom ke field form, preview data, lalu import:

```php
$crud = new GroceryCrud();
$crud->setTable('contacts');

// Enable import (default: true)
$crud->setImportable(true);

// Atau disable import untuk CRUD tertentu
// $crud->setImportable(false);
```

**Alur Import:**
1. Klik tombol **Import** di toolbar
2. Upload file CSV atau Excel (.xlsx)
3. Auto-detect column mapping (dicocokkan berdasarkan kemiripan nama dengan field form)
4. Preview data baris pertama
5. Klik **Import Data** untuk bulk insert

**CSV Template:**
- Klik **Download CSV template** untuk download template dengan semua field
- Klik **Customize** untuk memilih field mana yang akan dimasukkan ke template
- Template berisi header dengan label field (dari `displayAs()`) dan satu baris sample data

> **Catatan:** CSV import bekerja tanpa dependensi tambahan. XLSX membutuhkan `composer require phpoffice/phpspreadsheet`.

## Theme

```php
// Bootstrap 5 (default)
$crud->setTheme('bootstrap5');

// AdminLTE 4
$crud->setTheme('adminlte4');
```

Untuk membuat theme kustom, implement interface `GroceryCrud\Themes\ThemeInterface`.

## Dokumentasi API Lengkap

### Konfigurasi

| Method | Deskripsi |
|--------|-----------|
| `setTable(string $table, ?string $subject)` | Set tabel utama + subject |
| `setSubject(string $subject)` | Set judul/subject |
| `setTheme(string $theme)` | Set tema (`bootstrap5`, `adminlte4`) |
| `setLanguage(string $language)` | Set bahasa (`english`, `indonesian`) |
| `setPerPage(int $perPage)` | Item per halaman (default: 25) |
| `setSearchable(bool $searchable)` | Aktifkan/nonaktifkan search |
| `setExportable(bool $exportable)` | Aktifkan/nonaktifkan export |
| `setImportable(bool $importable)` | Aktifkan/nonaktifkan import CSV/Excel |
| `setSoftDelete(bool $enabled)` | Aktifkan soft delete |
| `withTrashed()` | Tampilkan record yang sudah di-soft-delete |
| `orderBy(string $field, string $direction)` | Default order |
| `where(array|string $key, mixed $value)` | WHERE condition |

### Columns & Fields

| Method | Deskripsi |
|--------|-----------|
| `setColumns(...$columns)` | Kolom yang ditampilkan di tabel |
| `setFields(...$fields)` | Field di form add/edit |
| `setAddFields(...$fields)` | Field khusus form add |
| `setEditFields(...$fields)` | Field khusus form edit |
| `displayAs(string $field, string $label)` | Label display untuk field |
| `setFieldType(string $field, string $type, array $options)` | Override tipe field (dropdown, enum, color, dll) |
| `setReadOnly(string $field)` | Field read-only di form |

### Relations

| Method | Deskripsi |
|--------|-----------|
| `setRelation(string $field, string $table, string $title, ?$where, ?$orderBy)` | Belongs_to relation |
| `setRelationNtoN(string $field, string $junction, string $pk, string $fk, string $target, string $title, ?$where, ?$orderBy)` | Many-to-many relation |

### Sub-Grid

| Method | Deskripsi |
|--------|-----------|
| `setSubGrid(string $field, string $table, string $fk, array $columns, array $labels, array $relations)` | Nested expandable table |

### Repeater Fields

| Method | Deskripsi |
|--------|-----------|
| `setRepeater(string $field, string $label, array $repeatables, string $preset, array $options)` | Repeatable group of sub-fields |

### Dynamic Form Conditions

| Method | Deskripsi |
|--------|-----------|
| `dependsOn(string $field, string $dependsOnField, mixed $value, string $action)` | Show/hide atau enable/disable field berdasarkan nilai field lain |

### Callbacks

| Method | Deskripsi |
|--------|-----------|
| `callbackBeforeInsert(callable)` | Sebelum insert |
| `callbackAfterInsert(callable)` | Setelah insert |
| `callbackBeforeUpdate(callable)` | Sebelum update |
| `callbackAfterUpdate(callable)` | Setelah update |
| `callbackBeforeDelete(callable)` | Sebelum delete |
| `callbackAfterDelete(callable)` | Setelah delete |
| `callbackColumn(string $field, callable)` | Format tampilan kolom di list |
| `callbackField(string $field, callable)` | Format field (add & edit) |
| `callbackAddField(string $field, callable)` | Format field di form add |
| `callbackEditField(string $field, callable)` | Format field di form edit |

### Validation

| Method | Deskripsi |
|--------|-----------|
| `required(string $field)` | Field wajib diisi |
| `unique(string $field)` | Field harus unik |
| `setRules(string $field, string $rules, ?string $label)` | Custom validation rules CI4 |

### Upload

| Method | Deskripsi |
|--------|-----------|
| `setUpload(string $field, array $config)` | Konfigurasi upload file (allowedTypes, maxSize, encryptFileName) |

### Actions

| Method | Deskripsi |
|--------|-----------|
| `setActions(string ...$actions)` | Set default actions (`add`, `edit`, `delete`) |
| `addAction(string $label, string $icon, string $url, string $cssClass)` | Custom action button per baris |

### Batch Actions

| Method | Deskripsi |
|--------|-----------|
| `setBatchAction(string $actionId, string $label)` | Tambah batch action (built-in: `delete_selected`, `restore_selected`) |
| `addBatchAction(string $actionId, string $label)` | Alias untuk `setBatchAction` |

### Column Filters

| Method | Deskripsi |
|--------|-----------|
| `setColumnFilter(string $field, string $type, array $options)` | Filter per-kolom (text, dropdown) |
| `setColumnFilterRelation(string $field, string $table, string $label, ?$key, ?$where, ?$order)` | Filter dropdown dari tabel relasi |

### Toolbar

| Method | Deskripsi |
|--------|-----------|
| `unsetFilters()` | Sembunyikan tombol Filters |
| `unsetColumns()` | Sembunyikan tombol Columns |
| `unsetSettings()` | Sembunyikan tombol Settings |

## Tipe Field yang Didukung

| Tipe | Deskripsi |
|------|-----------|
| `text` | Input text |
| `integer` | Input number integer |
| `numeric` | Input number decimal |
| `textarea` | Textarea |
| `richtext` | WYSIWYG editor (Quill.js) — HTML output |
| `email` | Input email |
| `password` | Input password |
| `dropdown` | Select dropdown |
| `enum` | Select dari ENUM database |
| `set` | Checkbox list |
| `date` | Input date |
| `datetime` | Input datetime-local |
| `time` | Input time |
| `true_false` | Switch toggle |
| `image` | File upload (image) |
| `file` | File upload |
| `color` | Color picker |
| `url` | Input URL |
| `phone` | Input tel |
| `read_only` | Read-only text |

## UI Features

- **Select All** — Checkbox di header untuk select/deselect semua baris
- **Sub-Grid** — Expandable row dengan nested table + loading state + header badge
- **Image Viewer** — Klik thumbnail untuk lihat gambar ukuran penuh di modal
- **Column Visibility** — Toggle show/hide kolom dari dropdown
- **Settings** — Simpan/muat/reset konfigurasi kolom ke localStorage
- **Sort** — Klik header kolom untuk sort asc/desc
- **Advanced Filters** — Multi-condition filter (contains, equals, starts with, ends with, greater/less than)
- **Import** — Upload CSV/Excel dengan auto-mapping, preview, bulk insert, dan template generator dengan field selection
- **Export** — Download CSV atau Excel dari tombol toolbar
- **Cache Busting** — Asset versioning otomatis dengan `?v=timestamp`

## License

MIT
