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
}
