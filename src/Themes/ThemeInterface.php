<?php

declare(strict_types=1);

namespace GroceryCrud\Themes;

interface ThemeInterface
{
    /**
     * Merender tampilan daftar (tabel).
     *
     * @param array<string, mixed> $data
     * @return string HTML output
     */
    public function renderList(array $data): string;

    /**
     * Merender form tambah.
     *
     * @param array<string, mixed> $data
     * @return string HTML output
     */
    public function renderAddForm(array $data): string;

    /**
     * Merender form edit.
     *
     * @param array<string, mixed> $data
     * @return string HTML output
     */
    public function renderEditForm(array $data): string;

    /**
     * Mendapatkan nama tema.
     */
    public function getName(): string;

    /**
     * Mendapatkan file CSS yang dibutuhkan.
     *
     * @return array<int, string>
     */
    public function getCssFiles(): array;

    /**
     * Mendapatkan file JS yang dibutuhkan.
     *
     * @return array<int, string>
     */
    public function getJsFiles(): array;

    /**
     * Merender sub-grid (tabel bersarang yang diperluas).
     *
     * @param array<string, mixed> $config Konfigurasi sub-grid
     * @param array<int, array<string, mixed>> $records Record sub-grid
     * @return string HTML output
     */
    public function renderSubGrid(array $config, array $records): string;

    /**
     * Merender form impor (upload file + pemetaan kolom).
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public function renderImportForm(array $data): string;
}
