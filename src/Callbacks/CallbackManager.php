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
     * Register a callback.
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
     * Get a registered callback.
     */
    public function get(string $hook): ?callable
    {
        return $this->callbacks[$hook] ?? null;
    }

    /**
     * Execute a before-hook callback.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed> Modified data
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
     * Execute an after-hook callback.
     *
     * @param  array<string, mixed> $data
     * @return mixed Callback return value
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
     * Execute a column value callback.
     *
     * @param  array<string, mixed> $row
     */
    public function executeColumnCallback(string $field, mixed $value, array $row): string
    {
        $callback = $this->get('column');
        if ($callback !== null) {
            // Column callbacks can be registered per-field
            $columnCallbacks = $this->callbacks['columnCallbacks'] ?? [];
            if (isset($columnCallbacks[$field])) {
                return (string) $columnCallbacks[$field]($value, $row);
            }
        }
        return (string) $value;
    }

    /**
     * Register a per-field column callback.
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
     * Register a per-field form callback (both add and edit).
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
     * Register a per-field add form callback.
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
     * Register a per-field edit form callback.
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
     * Get column callbacks.
     *
     * @return array<string, callable>
     */
    public function getColumnCallbacks(): array
    {
        return $this->callbacks['columnCallbacks'] ?? [];
    }

    /**
     * Get add field callbacks.
     *
     * @return array<string, callable>
     */
    public function getAddFieldCallbacks(): array
    {
        return $this->callbacks['addFieldCallbacks'] ?? [];
    }

    /**
     * Get edit field callbacks.
     *
     * @return array<string, callable>
     */
    public function getEditFieldCallbacks(): array
    {
        return $this->callbacks['editFieldCallbacks'] ?? [];
    }

    /**
     * Check if any callback exists for a hook.
     */
    public function has(string $hook): bool
    {
        return isset($this->callbacks[$hook]);
    }
}
