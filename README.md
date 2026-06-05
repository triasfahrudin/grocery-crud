# Grocery CRUD untuk CodeIgniter 4

Library CRUD generator full-featured untuk CodeIgniter 4. Terinspirasi dari Grocery CRUD untuk CI3.

## Fitur

- **CRUD Lengkap** — Create, Read, Update, Delete dengan AJAX
- **Relations** — Belongs_to & Many-to-many (NtoN)
- **Callbacks** — beforeInsert, afterInsert, beforeUpdate, afterUpdate, beforeDelete, afterDelete, callbackColumn, callbackField
- **Validation** — Validasi CI4 terintegrasi, unique, required
- **Upload** — File & image upload dengan thumbnail
- **Export** — CSV & Excel export
- **Theme System** — Bootstrap 5 & AdminLTE 4, mudah ditambahkan tema baru
- **Multi-language** — English & Indonesian bawaan
- **Search** — Pencarian real-time
- **Custom Actions** — Tombol aksi kustom
- **Field Type Detection** — Auto-detect tipe field dari database
- **Field Type Override** — Override tipe field manual

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

## Theme

Theme bisa diubah dengan `setTheme()`:

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
| `setTable(string $table, ?string $subject)` | Set tabel utama |
| `setSubject(string $subject)` | Set judul/subject |
| `setTheme(string $theme)` | Set tema (bootstrap5, adminlte4) |
| `setLanguage(string $language)` | Set bahasa (english, indonesian) |
| `setPerPage(int $perPage)` | Item per halaman |
| `setSearchable(bool $searchable)` | Aktifkan/nonaktifkan search |
| `setExportable(bool $exportable)` | Aktifkan/nonaktifkan export |

### Columns & Fields

| Method | Deskripsi |
|--------|-----------|
| `setColumns(...$columns)` | Kolom yang ditampilkan di tabel |
| `setFields(...$fields)` | Field di form add/edit |
| `setAddFields(...$fields)` | Field khusus form add |
| `setEditFields(...$fields)` | Field khusus form edit |
| `displayAs(string $field, string $label)` | Label display untuk field |
| `setFieldType(string $field, string $type, array $options = [])` | Override tipe field + opsi (dropdown, dll) |
| `setReadOnly(string $field)` | Field read-only |

### Relations

| Method | Deskripsi |
|--------|-----------|
| `setRelation($field, $relatedTable, $relatedTitleField, $where, $orderBy)` | Belongs_to relation |
| `setRelationNtoN($field, $junctionTable, $pkInJunction, $fkInJunction, $targetTable, $targetTitleField, $where, $orderBy)` | Many-to-many relation |

### Callbacks

| Method | Deskripsi |
|--------|-----------|
| `callbackBeforeInsert(callable)` | Sebelum insert |
| `callbackAfterInsert(callable)` | Setelah insert |
| `callbackBeforeUpdate(callable)` | Sebelum update |
| `callbackAfterUpdate(callable)` | Setelah update |
| `callbackBeforeDelete(callable)` | Sebelum delete |
| `callbackAfterDelete(callable)` | Setelah delete |
| `callbackColumn(string $field, callable)` | Format tampilan kolom |
| `callbackField(string $field, callable)` | Format field (add & edit) |
| `callbackAddField(string $field, callable)` | Format field di form add |
| `callbackEditField(string $field, callable)` | Format field di form edit |

### Validation

| Method | Deskripsi |
|--------|-----------|
| `required(string $field)` | Field wajib diisi |
| `unique(string $field)` | Field harus unik |
| `setRules(string $field, string $rules)` | Set custom validation rules |

### Upload

| Method | Deskripsi |
|--------|-----------|
| `setUpload(string $field, array $config)` | Konfigurasi upload file |

### Actions

| Method | Deskripsi |
|--------|-----------|
| `setActions(string ...$actions)` | Set default actions (add, edit, delete) |
| `addAction(string $label, string $icon, string $url)` | Tambah custom action button |

### Query

| Method | Deskripsi |
|--------|-----------|
| `orderBy(string $field, string $direction)` | Default order |
| `where($key, $value)` | WHERE condition |

## Tipe Field yang Didukung

| Tipe | Deskripsi |
|------|-----------|
| `text` | Input text |
| `integer` | Input number integer |
| `numeric` | Input number decimal |
| `textarea` | Textarea |
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

## License

MIT
