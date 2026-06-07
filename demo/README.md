# Demo: dependsOn — Dynamic Form Conditions

Demo untuk fitur **Dynamic Form Conditions** (`dependsOn`) pada Grocery CRUD untuk CodeIgniter 4.

## Skenario

**Tabel:** `products`

| Field | Tipe | dependsOn? |
|-------|------|------------|
| `name` | text | — |
| `price` | decimal | — |
| `has_discount` | true_false (switch) | **Controller** |
| `discount_price` | decimal | show/hide by `has_discount = true` |
| `discount_percent` | integer | show/hide by `has_discount = true` |
| `requires_shipping` | true_false (switch) | **Controller** |
| `shipping_weight` | decimal | enable/disable by `requires_shipping = true` |
| `shipping_notes` | textarea | enable/disable by `requires_shipping = true` |
| `is_active` | true_false (switch) | — |

### Perilaku

1. **discount_price & discount_percent** — Hanya muncul saat switch **"Have Discount?"** ON.
   - Saat OFF: field hilang, nilainya tidak dikirim ke server.
2. **shipping_weight & shipping_notes** — Hanya bisa diisi saat switch **"Requires Shipping?"** ON.
   - Saat OFF: field tampil tapi *disabled* (tidak bisa diisi, tidak dikirim).

## Setup

### 1. Import Database

Jalankan SQL dump:

```bash
mysql -u root -p nama_database < demo/demo_depends_on.sql
```

### 2. Setup Route

Tambahkan di `app/Config/Routes.php`:

```php
$routes->get('demo-depends-on', 'DemoDependsOn::index');
```

### 3. Akses Demo

Buka browser:

```
http://localhost:8080/demo-depends-on
```

## Cara Kerja

### PHP

```php
// Sembunyikan discount_price jika has_discount tidak dicentang
$crud->dependsOn('discount_price', 'has_discount', true);

// Nonaktifkan shipping_weight jika requires_shipping tidak dicentang
$crud->dependsOn('shipping_weight', 'requires_shipping', true, 'enable');
```

### JavaScript

- `initDependsOn()` membaca atribut `data-depends-on` dari field wrapper.
- Mendengarkan event `change` pada controller field (switch/checkbox/dropdown).
- Untuk `action = 'show'`: toggle visibility, set `disabled` saat hidden.
- Untuk `action = 'enable'`: toggle `disabled`, tambah/hapus class `.gc-depends-disabled`.
- Cleanup event listener saat modal ditutup.
