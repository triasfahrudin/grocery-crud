<?php

declare(strict_types=1);

namespace GroceryCrud\Themes;

interface ThemeInterface
{
    /**
     * Render the list (table) view.
     *
     * @param array<string, mixed> $data
     * @return string HTML output
     */
    public function renderList(array $data): string;

    /**
     * Render the add form.
     *
     * @param array<string, mixed> $data
     * @return string HTML output
     */
    public function renderAddForm(array $data): string;

    /**
     * Render the edit form.
     *
     * @param array<string, mixed> $data
     * @return string HTML output
     */
    public function renderEditForm(array $data): string;

    /**
     * Get the theme name.
     */
    public function getName(): string;

    /**
     * Get required CSS files.
     *
     * @return array<int, string>
     */
    public function getCssFiles(): array;

    /**
     * Get required JS files.
     *
     * @return array<int, string>
     */
    public function getJsFiles(): array;

    /**
     * Render a sub-grid (expanded nested table).
     *
     * @param array<string, mixed> $config Sub-grid configuration
     * @param array<int, array<string, mixed>> $records Sub-grid records
     * @return string HTML output
     */
    public function renderSubGrid(array $config, array $records): string;

    /**
     * Render the import form (file upload + column mapping).
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public function renderImportForm(array $data): string;
}
