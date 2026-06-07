<?php

namespace App\Controllers;

use GroceryCrud\GroceryCrud;

/**
 * Demo Controller: dependsOn — Dynamic Form Conditions
 *
 * Menunjukkan penggunaan dependsOn() untuk show/hide dan enable/disable
 * field berdasarkan nilai field lain.
 *
 * @see https://github.com/triasfahrudin/grocery-crud
 */
class DemoDependsOn extends BaseController
{
    /**
     * CRUD Products dengan Dynamic Form Conditions.
     *
     * Skenario:
     * - discount_price & discount_percent tampil hanya jika has_discount dicentang
     * - shipping_weight & shipping_notes nonaktif jika requires_shipping tidak dicentang
     */
    public function index()
    {
        $crud = new GroceryCrud();
        $crud->setTable('products');
        $crud->setSubject('Product');

        // ─── Columns ─────────────────────────────────────────
        $crud->setColumns(
            'name',
            'price',
            'has_discount',
            'discount_price',
            'requires_shipping',
            'shipping_weight',
            'is_active'
        );

        // ─── Form Fields ─────────────────────────────────────
        $crud->setFields(
            'name',
            'price',
            'has_discount',
            'discount_price',
            'discount_percent',
            'requires_shipping',
            'shipping_weight',
            'shipping_notes',
            'is_active'
        );

        // ─── Labels ──────────────────────────────────────────
        $crud->displayAs('name', 'Product Name');
        $crud->displayAs('price', 'Base Price');
        $crud->displayAs('has_discount', 'Have Discount?');
        $crud->displayAs('discount_price', 'Discount Price');
        $crud->displayAs('discount_percent', 'Discount (%)');
        $crud->displayAs('requires_shipping', 'Requires Shipping?');
        $crud->displayAs('shipping_weight', 'Weight (kg)');
        $crud->displayAs('shipping_notes', 'Shipping Notes');
        $crud->displayAs('is_active', 'Active');

        // ─── Field Types ─────────────────────────────────────
        $crud->setFieldType('has_discount', 'true_false');
        $crud->setFieldType('requires_shipping', 'true_false');
        $crud->setFieldType('is_active', 'true_false');
        $crud->setFieldType('discount_percent', 'integer');

        // ─── Dynamic Form Conditions (dependsOn) ─────────────
        //
        // ACTION 'show': Sembunyikan field jika kondisi tidak terpenuhi
        //   → discount_price & discount_percent hanya muncul saat has_discount = true
        //
        $crud->dependsOn('discount_price', 'has_discount', true, 'show');
        $crud->dependsOn('discount_percent', 'has_discount', true, 'show');
        //
        // ACTION 'enable': Nonaktifkan field jika kondisi tidak terpenuhi
        //   → shipping_weight & shipping_notes hanya bisa diisi saat requires_shipping = true
        //
        $crud->dependsOn('shipping_weight', 'requires_shipping', true, 'enable');
        $crud->dependsOn('shipping_notes', 'requires_shipping', true, 'enable');

        // ─── Validation ──────────────────────────────────────
        $crud->required('name');
        $crud->required('price');
        $crud->setRules('price', 'numeric|greater_than[0]');
        $crud->setRules('discount_price', 'numeric|greater_than[0]');
        $crud->setRules('discount_percent', 'integer|greater_than_equal_to[0]|less_than_equal_to[100]');
        $crud->setRules('shipping_weight', 'numeric|greater_than[0]');

        // ─── Relations (jika ada) ────────────────────────────
        // (tidak ada relasi dalam demo ini)

        // ─── Export & Batch ──────────────────────────────────
        $crud->setExportable(true);

        // ─── Render ──────────────────────────────────────────
        return $crud->render();
    }
}
