<?php

declare(strict_types=1);

namespace GroceryCrud\Callbacks;

use GroceryCrud\Exceptions\GroceryCrudException;

class CallbackManager
{
    /** @var array<string, callable|null> */
    private array $callbacks = [];

    private const VALID_HOOKS = [
        'beforeInsert',
        'afterInsert',
        'beforeUpdate',
        'afterUpdate',
        'beforeDelete',
        'afterDelete',
        'column',
        'addField',
        'editField',
    ];

    /**
     * Daftarkan callback.
     *
     * @throws GroceryCrudException
     */
    public function register(string $hook, ?callable $callback): void
    {
        if (!in_array($hook, self::VALID_HOOKS, true)) {
            throw GroceryCrudException::invalidCallback($hook);
        }

        $this->callbacks[$hook] = $callback;
    }

    /**
     * Dapatkan callback yang terdaftar.
     */
    public function get(string $hook): ?callable
    {
        return $this->callbacks[$hook] ?? null;
    }

    /**
     * Jalankan callback before-hook.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed> Data yang telah dimodifikasi
     */
    public function executeBefore(string $hook, array $data): array
    {
        $callback = $this->get($hook);
        if ($callback !== null) {
            $result = $callback($data);
            if (is_array($result)) {
                return $result;
            }
        }
        return $data;
    }

    /**
     * Jalankan callback after-hook.
     *
     * @param  array<string, mixed> $data
     * @return mixed Nilai kembalian callback
     */
    public function executeAfter(string $hook, array $data): mixed
    {
        $callback = $this->get($hook);
        if ($callback !== null) {
            return $callback($data);
        }
        return true;
    }

    /**
     * Jalankan callback nilai kolom.
     *
     * @param  array<string, mixed> $row
     */
    public function executeColumnCallback(string $field, mixed $value, array $row): string
    {
        $callback = $this->get('column');
        if ($callback !== null) {
            // Callback kolom dapat didaftarkan per-field
            $columnCallbacks = $this->callbacks['columnCallbacks'] ?? [];
            if (isset($columnCallbacks[$field])) {
                return (string) $columnCallbacks[$field]($value, $row);
            }
        }
        return (string) $value;
    }

    /**
     * Daftarkan callback kolom per-field.
     *
     * @param string   $field
     * @param callable $callback function($value, $row): string
     */
    public function registerColumnCallback(string $field, callable $callback): void
    {
        if (!isset($this->callbacks['columnCallbacks'])) {
            $this->callbacks['columnCallbacks'] = [];
        }
        $this->callbacks['columnCallbacks'][$field] = $callback;
    }

    /**
     * Daftarkan callback form per-field (add dan edit).
     *
     * @param string   $field
     * @param callable $callback function($value, $row): string
     */
    public function registerFieldCallback(string $field, callable $callback): void
    {
        $this->registerAddFieldCallback($field, $callback);
        $this->registerEditFieldCallback($field, $callback);
    }

    /**
     * Daftarkan callback form add per-field.
     *
     * @param string   $field
     * @param callable $callback function($value, $row): string
     */
    public function registerAddFieldCallback(string $field, callable $callback): void
    {
        if (!isset($this->callbacks['addFieldCallbacks'])) {
            $this->callbacks['addFieldCallbacks'] = [];
        }
        $this->callbacks['addFieldCallbacks'][$field] = $callback;
    }

    /**
     * Daftarkan callback form edit per-field.
     *
     * @param string   $field
     * @param callable $callback function($value, $row): string
     */
    public function registerEditFieldCallback(string $field, callable $callback): void
    {
        if (!isset($this->callbacks['editFieldCallbacks'])) {
            $this->callbacks['editFieldCallbacks'] = [];
        }
        $this->callbacks['editFieldCallbacks'][$field] = $callback;
    }

    /**
     * Dapatkan callback kolom.
     *
     * @return array<string, callable>
     */
    public function getColumnCallbacks(): array
    {
        return $this->callbacks['columnCallbacks'] ?? [];
    }

    /**
     * Dapatkan callback field add.
     *
     * @return array<string, callable>
     */
    public function getAddFieldCallbacks(): array
    {
        return $this->callbacks['addFieldCallbacks'] ?? [];
    }

    /**
     * Dapatkan callback field edit.
     *
     * @return array<string, callable>
     */
    public function getEditFieldCallbacks(): array
    {
        return $this->callbacks['editFieldCallbacks'] ?? [];
    }

    /**
     * Periksa apakah ada callback untuk suatu hook.
     */
    public function has(string $hook): bool
    {
        return isset($this->callbacks[$hook]);
    }
}
