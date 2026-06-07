<?php

declare(strict_types=1);

namespace GroceryCrud;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Validation\Validation;
use Config\Database;
use Config\Services;
use GroceryCrud\Callbacks\CallbackManager;
use GroceryCrud\Config\Config as GCConfig;
use GroceryCrud\Exceptions\GroceryCrudException;
use GroceryCrud\Export\CsvExport;
use GroceryCrud\Export\ExcelExport;
use GroceryCrud\Export\PdfExport;
use GroceryCrud\Fields\FieldType;
use GroceryCrud\Models\CrudModel;
use GroceryCrud\Relations\RelationManager;
use GroceryCrud\Renderers\TableRenderer;
use GroceryCrud\Themes\Bootstrap5Theme;
use GroceryCrud\Themes\ThemeInterface;
use GroceryCrud\Upload\UploadManager;
use GroceryCrud\Validation\ValidationManager;

class GroceryCrud
{
    private GCConfig $config;
    private BaseConnection $db;
    private CrudModel $model;
    private RelationManager $relationManager;
    private CallbackManager $callbackManager;
    private ValidationManager $validationManager;
    private UploadManager $uploadManager;
    private TableRenderer $renderer;
    private ThemeInterface $theme;

    /** @var array<string, string> */
    private array $languageStrings = [];

    /** @var array<int, string> */
    private array $columns = [];

    /** @var array<int, string> */
    private array $fields = [];

    /** @var array<int, string> */
    private array $addFields = [];

    /** @var array<int, string> */
    private array $editFields = [];

    /** @var array<string, string> */
    private array $columnLabels = [];

    /** @var array<int, string> */
    private array $defaultActions = ['add', 'edit', 'delete'];

    /** @var array<int, string> */
    private array $actions = [];

    /** @var array<int, array<string, string>> */
    private array $customActions = [];

    /** @var array<string, string> */
    private array $orderBy = [];

    /** @var array<string, mixed> */
    private array $where = [];

    /** @var array<string, array<int, mixed>> */
    private array $whereIn = [];

    private string $subject = '';
    private string $table = '';
    private string $primaryKey = 'id';
    private int $perPage;
    private bool $searchable = true;
    private bool $useDatatables;
    private bool $enableExport;
    private bool $enablePrintView = true;
    private bool $enablePdfExport = true;
    private bool $initialized = false;

    /** @var array<string, array<string, mixed>> */
    private array $uploadFieldConfigs = [];

    /** @var array<string, array<int, string>> */
    private array $enumCache = [];

    /** @var array<string, array{type: string, options?: array}> */
    private array $fieldTypeOverrides = [];

    /** @var array<string, array<string>> Role-based permissions: role => [actions] */
    private array $permissions = [];

    /** @var ?callable Callback to resolve current user's role: fn(): ?string */
    private $permissionCallback = null;

    /** @var ?string Cached current user role */
    private ?string $userRole = null;

    /** @var bool Enable inline editing on table cells */
    private bool $enableInlineEditing = false;

    /** @var array<int, string> Columns that are editable inline (empty = all columns) */
    private array $inlineEditColumns = [];

    /** @var array<int, string> */
    private array $requiredFields = [];

    /** @var array<int, string> */
    private array $readOnlyFields = [];

    /** @var array<int, string> */
    private array $uniqueFields = [];

    /** @var array<string, array{type: string, options?: array}> */
    private array $columnFilters = [];

    /** @var array<string, array{table: string, labelField: string, keyField: string, where: ?string, order: ?string}> */
    private array $columnFilterRelations = [];

    /** @var bool Enable soft delete on the model */
    private bool $softDelete = false;

    /** @var bool Whether we are viewing trashed records */
    private bool $trashedView = false;

    /** @var array<string, string> */
    private array $batchActions = [];

    /** @var array<string, array{label: string, repeatables: array<int, array{name: string, label: string, type: string, rules?: string, options?: array}>, preset: string, foreignKey?: string, relatedTable?: string, relatedKey?: string}> */
    private array $repeaterFields = [];

    /** @var array<string, array{relatedTable: string, foreignKey: string, columns: array, columnLabels: array}> */
    private array $subGrids = [];

    /** @var array<string, array{field: string, value: mixed, action: string}> */
    private array $dependsOn = [];

    private bool $enableFilters = true;
    private bool $enableColumns = true;
    private bool $enableSettings = true;

    private string $crudId;

    public function __construct(?GCConfig $config = null, ?BaseConnection $db = null)
    {
        $this->config = $config ?? new GCConfig();
        $this->db = $db ?? Database::connect();
        $this->callbackManager = new CallbackManager();
        $this->uploadManager = new UploadManager($this->config);
        $this->renderer = new TableRenderer($this->config);
        $this->perPage = $this->config->perPage;
        $this->useDatatables = $this->config->useDatatables;
        $this->enableExport = $this->config->enableExport;
        $this->enablePrintView = $this->config->enablePrintView;
        $this->enablePdfExport = $this->config->enablePdfExport;
        $this->actions = $this->config->defaultActions;
        $this->crudId = 'crud_' . uniqid();

        // Set default theme
        $themeClass = $this->config->themes[$this->config->defaultTheme] ?? Bootstrap5Theme::class;
        $this->theme = new $themeClass();

        // Load default language
        $langClass = $this->config->languages[$this->config->defaultLanguage] ?? $this->config->languages['english'];
        if (class_exists($langClass)) {
            $langObj = new $langClass();
            $this->languageStrings = $langObj->strings;
            $this->theme->setLanguageStrings($this->languageStrings);
        }
    }

    // ======== Fluent Configuration API ========

    /**
     * Set the main table and optional subject.
     */
    public function setTable(string $table, ?string $subject = null): self
    {
        $this->table = $table;
        $this->subject = $subject ?? ucfirst(str_replace('_', ' ', $table));

        // Initialize model and relation manager
        $this->model = new CrudModel($this->db, $table);
        $this->primaryKey = $this->model->getPrimaryKey();
        $this->relationManager = new RelationManager($this->db, $table, $this->primaryKey);
        $this->validationManager = new ValidationManager(
            Services::validation(),
            $this->db,
            $table,
            $this->primaryKey
        );
        $this->initialized = true;

        return $this;
    }

    /**
     * Set the subject name.
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set columns to display in the table.
     */
    public function setColumns(...$columns): self
    {
        $this->columns = is_array($columns[0] ?? null) ? $columns[0] : $columns;
        return $this;
    }

    /**
     * Set fields for add/edit forms.
     */
    public function setFields(...$fields): self
    {
        $this->fields = is_array($fields[0] ?? null) ? $fields[0] : $fields;
        return $this;
    }

    /**
     * Set fields for add form only.
     */
    public function setAddFields(...$fields): self
    {
        $this->addFields = is_array($fields[0] ?? null) ? $fields[0] : $fields;
        return $this;
    }

    /**
     * Set fields for edit form only.
     */
    public function setEditFields(...$fields): self
    {
        $this->editFields = is_array($fields[0] ?? null) ? $fields[0] : $fields;
        return $this;
    }

    /**
     * Set a display label for a field/column.
     */
    public function displayAs(string $field, string $label): self
    {
        $this->columnLabels[$field] = $label;
        return $this;
    }

    /**
     * Set a relation (belongs_to).
     */
    public function setRelation(
        string $field,
        string $relatedTable,
        string $relatedTitleField,
        ?string $where = null,
        ?string $orderBy = null
    ): self {
        $this->ensureInitialized();
        $this->relationManager->setRelation($field, $relatedTable, $relatedTitleField, $where, $orderBy);

        // Register with model for display value fetching
        $this->model->setRelationField($field, [
            'relatedTable'      => $relatedTable,
            'relatedTitleField' => $relatedTitleField,
            'foreignKey'        => $field,
        ]);

        return $this;
    }

    /**
     * Set an N-to-N relation (many-to-many).
     */
    public function setRelationNtoN(
        string $field,
        string $junctionTable,
        string $primaryKeyInJunction,
        string $foreignKeyInJunction,
        string $targetTable,
        string $targetTitleField,
        ?string $where = null,
        ?string $orderBy = null
    ): self {
        $this->ensureInitialized();
        $this->relationManager->setRelationNtoN(
            $field, $junctionTable, $primaryKeyInJunction,
            $foreignKeyInJunction, $targetTable, $targetTitleField,
            $where, $orderBy
        );

        // Register with model
        $this->model->setRelationNtoN($field, [
            'junctionTable'        => $junctionTable,
            'primaryKeyInJunction' => $primaryKeyInJunction,
            'foreignKeyInJunction' => $foreignKeyInJunction,
            'targetTable'          => $targetTable,
            'targetTitleField'     => $targetTitleField,
        ]);

        return $this;
    }

    // ======== Callbacks ========

    public function callbackBeforeInsert(callable $callback): self
    {
        $this->callbackManager->register('beforeInsert', $callback);
        return $this;
    }

    public function callbackAfterInsert(callable $callback): self
    {
        $this->callbackManager->register('afterInsert', $callback);
        return $this;
    }

    public function callbackBeforeUpdate(callable $callback): self
    {
        $this->callbackManager->register('beforeUpdate', $callback);
        return $this;
    }

    public function callbackAfterUpdate(callable $callback): self
    {
        $this->callbackManager->register('afterUpdate', $callback);
        return $this;
    }

    public function callbackBeforeDelete(callable $callback): self
    {
        $this->callbackManager->register('beforeDelete', $callback);
        return $this;
    }

    public function callbackAfterDelete(callable $callback): self
    {
        $this->callbackManager->register('afterDelete', $callback);
        return $this;
    }

    /**
     * Callback for displaying a column value.
     */
    public function callbackColumn(string $field, callable $callback): self
    {
        $this->callbackManager->registerColumnCallback($field, $callback);
        return $this;
    }

    /**
     * Callback for both add and edit form field.
     */
    public function callbackField(string $field, callable $callback): self
    {
        $this->callbackManager->registerFieldCallback($field, $callback);
        return $this;
    }

    /**
     * Callback for add form field.
     */
    public function callbackAddField(string $field, callable $callback): self
    {
        $this->callbackManager->registerAddFieldCallback($field, $callback);
        return $this;
    }

    /**
     * Callback for edit form field.
     */
    public function callbackEditField(string $field, callable $callback): self
    {
        $this->callbackManager->registerEditFieldCallback($field, $callback);
        return $this;
    }

    // ======== Validation ========

    /**
     * Set validation rules for a field.
     */
    public function setRules(string $field, string $rules, ?string $label = null): self
    {
        $this->validationManager->setRules($field, $rules, $label);
        return $this;
    }

    /**
     * Mark a field as required.
     */
    public function required(string $field): self
    {
        $this->validationManager->required($field);
        $this->requiredFields[] = $field;
        return $this;
    }

    /**
     * Mark a field as unique.
     */
    public function unique(string $field): self
    {
        $this->validationManager->unique($field);
        $this->uniqueFields[] = $field;
        return $this;
    }

    // ======== Upload ========

    /**
     * Configure upload for a field.
     */
    public function setUpload(string $field, array $config = []): self
    {
        $this->uploadFieldConfigs[$field] = $config;
        $this->uploadManager->configureField($field, $config);
        return $this;
    }

    // ======== Theme & Language ========

    /**
     * Set the rendering theme.
     */
    public function setTheme(string $theme): self
    {
        if (!isset($this->config->themes[$theme])) {
            throw GroceryCrudException::themeNotFound($theme);
        }

        $themeClass = $this->config->themes[$theme];
        $this->theme = new $themeClass();
        $this->theme->setLanguageStrings($this->languageStrings);

        return $this;
    }

    /**
     * Set language.
     */
    public function setLanguage(string $language): self
    {
        if (!isset($this->config->languages[$language])) {
            return $this;
        }

        $langClass = $this->config->languages[$language];
        if (class_exists($langClass)) {
            $langObj = new $langClass();
            $this->languageStrings = $langObj->strings;
            $this->theme->setLanguageStrings($this->languageStrings);
        }

        return $this;
    }

    // ======== Actions ========

    /**
     * Set which default actions to show.
     */
    public function setActions(string ...$actions): self
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * Add a custom action button.
     */
    public function addAction(string $label, string $icon, string $url, string $cssClass = ''): self
    {
        $this->customActions[] = [
            'label'    => $label,
            'icon'     => $icon,
            'url'      => $url,
            'cssClass' => $cssClass,
        ];
        return $this;
    }

    // ======== Soft Delete ========

    /**
     * Enable soft delete for the CRUD.
     *
     * When enabled, delete() will set deleted_at instead of hard-deleting.
     * Use withTrashed() to view/restore soft-deleted records.
     */
    public function setSoftDelete(bool $enabled = true): self
    {
        $this->softDelete = $enabled;
        $this->ensureInitialized();
        $this->model->setSoftDelete($enabled);
        return $this;
    }

    /**
     * Show records including soft-deleted ones, as well as restore actions.
     * Must be called before render().
     */
    public function withTrashed(): self
    {
        $this->trashedView = true;
        $this->ensureInitialized();
        $this->model->withTrashed();
        return $this;
    }

    // ======== Query Configuration ========

    /**
     * Set default order.
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy[] = ['field' => $field, 'direction' => strtoupper($direction)];
        return $this;
    }

    /**
     * Add WHERE condition.
     */
    public function where(array|string $key, mixed $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->where[$k] = $v;
            }
        } else {
            $this->where[$key] = $value;
        }
        return $this;
    }

    /**
     * Set the number of items per page.
     */
    public function setPerPage(int $perPage): self
    {
        $this->perPage = $perPage > 0 ? $perPage : $this->config->perPage;
        return $this;
    }

    /**
     * Enable/disable search.
     */
    public function setSearchable(bool $searchable): self
    {
        $this->searchable = $searchable;
        return $this;
    }

    /**
     * Enable/disable export.
     */
    public function setExportable(bool $exportable): self
    {
        $this->enableExport = $exportable;
        return $this;
    }

    /**
     * Enable/disable print view.
     */
    public function setPrintView(bool $enable): self
    {
        $this->enablePrintView = $enable;
        return $this;
    }

    /**
     * Enable/disable PDF export.
     */
    public function setPdfExport(bool $enable): self
    {
        $this->enablePdfExport = $enable;
        return $this;
    }

    // ======== Permissions / RBAC ========

    /**
     * Set allowed actions for a specific role.
     *
     * Available actions: 'add', 'edit', 'delete', 'view', 'export'
     *
     * Example:
     *   $crud->setPermission('admin', ['add', 'edit', 'delete', 'view', 'export']);
     *   $crud->setPermission('editor', ['add', 'edit', 'view', 'export']);
     *   $crud->setPermission('viewer', ['view', 'export']);
     *
     * @param string   $role    Role name (e.g. 'admin', 'editor', 'viewer')
     * @param string[] $actions Allowed actions for this role
     */
    public function setPermission(string $role, array $actions): self
    {
        $this->permissions[$role] = $actions;
        return $this;
    }

    /**
     * Set a callback to resolve the current user's role.
     *
     * The callback should return a string (role name) or null (unauthenticated).
     * When null is returned and permissions are defined, all actions are denied.
     *
     * Example:
     *   $crud->setPermissionCallback(fn() => session()->get('role'));
     *
     * @param callable $callback fn(): ?string
     */
    public function setPermissionCallback(callable $callback): self
    {
        $this->permissionCallback = $callback;
        return $this;
    }

    // ======== Inline Editing ========

    /**
     * Enable or disable inline editing on the table.
     *
     * When enabled, users can double-click on table cells to edit values directly.
     *
     * @param bool $enable
     * @return self
     */
    public function setInlineEditing(bool $enable = true): self
    {
        $this->enableInlineEditing = $enable;
        return $this;
    }

    /**
     * Specify which columns are editable inline.
     *
     * If called with no arguments or an empty array, all visible columns are editable.
     * Relation/foreign-key columns and date columns will show appropriate inputs.
     *
     * @param array<int, string> $columns
     * @return self
     */
    public function setInlineEditColumns(array $columns): self
    {
        $this->inlineEditColumns = $columns;
        return $this;
    }

    /**
     * Determine inline editing field type and options for a column.
     *
     * @return array{type: string, options?: array<string, string>}
     */
    private function getInlineFieldInfo(string $field): array
    {
        $this->ensureInitialized();

        // Field type override takes priority
        if (isset($this->fieldTypeOverrides[$field])) {
            $override = $this->fieldTypeOverrides[$field];
            return [
                'type'    => $this->mapFieldTypeForInline($override['type']),
                'options' => $override['options'] ?? [],
            ];
        }

        // Relation field (belongs_to) => dropdown with options
        if ($this->relationManager->hasRelation($field) && $this->relationManager->getRelationType($field) === 'belongs_to') {
            $relData = $this->relationManager->getRelationData($field);
            $options = [];
            foreach ($relData as $item) {
                $options[(string) $item['id']] = $item['title'];
            }
            return ['type' => 'select', 'options' => $options];
        }

        // ENUM => dropdown with enum options
        $enumValues = $this->model->getEnumValues($field);
        if (!empty($enumValues)) {
            $options = array_combine($enumValues, $enumValues);
            return ['type' => 'select', 'options' => $options];
        }

        // Database type detection
        $dbType = $this->model->getFieldType($field);
        $detected = FieldType::detect($dbType ?? 'text', []);

        return [
            'type'    => $this->mapFieldTypeForInline($detected->value),
            'options' => [],
        ];
    }

    /**
     * Map GroceryCRUD field types to inline input types.
     */
    private function mapFieldTypeForInline(string $type): string
    {
        return match ($type) {
            'integer', 'numeric' => 'number',
            'date'               => 'date',
            'datetime'           => 'datetime',
            'time'               => 'time',
            'email'              => 'email',
            'url'                => 'url',
            'phone'              => 'tel',
            'boolean', 'true_false' => 'boolean',
            'textarea', 'text areas' => 'textarea',
            'dropdown', 'enum', 'relation' => 'select',
            default              => 'text',
        };
    }

    /**
     * Check if the current user has permission for the given action.
     */
    private function hasPermission(string $action): bool
    {
        // If no permissions defined, allow all
        if (empty($this->permissions) && $this->permissionCallback === null) {
            return true;
        }

        // Resolve user role from callback (lazy, once)
        if ($this->permissionCallback !== null && $this->userRole === null) {
            $this->userRole = call_user_func($this->permissionCallback);
        }

        // If user has a known role, check its permissions
        if ($this->userRole !== null && isset($this->permissions[$this->userRole])) {
            return in_array($action, $this->permissions[$this->userRole], true);
        }

        // Role not found in permissions map or unauthenticated: deny
        return false;
    }

    /**
     * Set a field as read-only.
     */
    public function setReadOnly(string $field): self
    {
        $this->readOnlyFields[] = $field;
        return $this;
    }

    /**
     * Override auto-detected field type.
     */
    public function setFieldType(string $field, string $type, array $options = []): self
    {
        $this->fieldTypeOverrides[$field] = ['type' => $type, 'options' => $options];
        return $this;
    }

    /**
     * Define a dynamic form condition: show/hide or enable/disable a field
     * based on the value of another field.
     *
     * Example:
     *   $crud->dependsOn('discount_price', 'has_discount', true);
     *   // discount_price is shown only when has_discount is checked (true)
     *
     *   $crud->dependsOn('shipping_address', 'same_as_billing', false, 'enable');
     *   // shipping_address is disabled when same_as_billing is checked (true)
     *
     * @param string $field         The field that depends on another field
     * @param string $dependsOnField The controlling field name
     * @param mixed  $value         The value that triggers the action
     * @param string $action        'show' (hide when not matching) or 'enable' (disable when not matching)
     */
    public function dependsOn(
        string $field,
        string $dependsOnField,
        mixed $value,
        string $action = 'show'
    ): self {
        $this->dependsOn[$field] = [
            'field'  => $dependsOnField,
            'value'  => $value,
            'action' => $action,
        ];
        return $this;
    }

    /**
     * Add a column filter which renders a filter control in the table header.
     *
     * Supported types: 'text', 'dropdown'
     *
     * @param string $field   Column name
     * @param string $type    Filter type ('text' or 'dropdown')
     * @param array  $options For 'dropdown': ['1' => 'Active', '0' => 'Inactive']
     */
    public function setColumnFilter(string $field, string $type, array $options = []): self
    {
        $this->columnFilters[$field] = ['type' => $type, 'options' => $options];
        return $this;
    }

    /**
     * Set a column filter with options dynamically fetched from a related table.
     *
     * @param string      $field       Column name in current table
     * @param string      $table       Related table name
     * @param string      $labelField  Field to display as option label
     * @param string|null $keyField    Key field (default: primary key of related table)
     * @param string|null $where       Optional WHERE condition (e.g., "status = 'active'")
     * @param string|null $order       Optional ORDER BY (e.g., "name ASC")
     */
    public function setColumnFilterRelation(string $field, string $table, string $labelField, ?string $keyField = null, ?string $where = null, ?string $order = null): self
    {
        $this->columnFilterRelations[$field] = [
            'table'      => $table,
            'labelField' => $labelField,
            'keyField'   => $keyField ?? 'id',
            'where'      => $where,
            'order'      => $order,
        ];
        return $this;
    }

    /**
     * Set a batch action.
     *
     * Built-in actions: 'delete_selected'
     *
     * @param string $actionId
     * @param string $label
     */
    public function setBatchAction(string $actionId, string $label): self
    {
        $this->batchActions[$actionId] = $label;
        return $this;
    }

    /**
     * Alias for setBatchAction.
     */
    public function addBatchAction(string $actionId, string $label): self
    {
        return $this->setBatchAction($actionId, $label);
    }

    /**
     * Define a repeater field (repeatable group of sub-fields).
     */
    public function setRepeater(string $field, string $label, array $repeatables, string $preset = 'json', array $options = []): self
    {
        $this->repeaterFields[$field] = [
            'label'       => $label,
            'repeatables' => $repeatables,
            'preset'      => $preset,
            'foreignKey'  => $options['foreignKey'] ?? null,
            'relatedTable' => $options['relatedTable'] ?? null,
            'relatedKey'  => $options['relatedKey'] ?? 'id',
        ];
        return $this;
    }

    /**
     * Define a sub-grid (nested CRUD table) that renders related records
     * in an expandable row below each parent record.
     *
     * @param string $field           Virtual field name (identifier, not a real column)
     * @param string $relatedTable    Related table name
     * @param string $foreignKey      FK in related table pointing to parent
     * @param array  $columns         Columns to display in sub-grid
     * @param array  $columnLabels    Optional column labels
     * @param array  $columnRelations Optional relation lookups for columns.
     *                                Format: ['column' => ['relatedTable', 'displayField', 'localKey', 'foreignKey']]
     *                                Example: ['tag_id' => ['tags', 'name', 'tag_id', 'id']]
     */
    public function setSubGrid(string $field, string $relatedTable, string $foreignKey, array $columns, array $columnLabels = [], array $columnRelations = []): self
    {
        $this->ensureInitialized();
        $this->subGrids[$field] = [
            'relatedTable'     => $relatedTable,
            'foreignKey'       => $foreignKey,
            'columns'          => $columns,
            'columnLabels'     => $columnLabels,
            'columnRelations'  => $columnRelations,
        ];
        $this->model->setSubGrid($field, [
            'relatedTable'     => $relatedTable,
            'foreignKey'       => $foreignKey,
            'columns'          => $columns,
            'columnLabels'     => $columnLabels,
            'columnRelations'  => $columnRelations,
        ]);
        return $this;
    }

    /**
     * Remove the Filters button from the datagrid toolbar.
     */
    public function unsetFilters(): self
    {
        $this->enableFilters = false;
        return $this;
    }

    /**
     * Remove the Columns button from the datagrid toolbar.
     */
    public function unsetColumns(): self
    {
        $this->enableColumns = false;
        return $this;
    }

    /**
     * Remove the Settings button from the datagrid toolbar.
     */
    public function unsetSettings(): self
    {
        $this->enableSettings = false;
        return $this;
    }

    // ======== Render Methods ========

    /**
     * Render the full CRUD interface (list + actions).
     * In AJAX context, returns JSON; otherwise returns HTML.
     *
     * @return ResponseInterface|string
     */
    public function render(): ResponseInterface|string
    {
        $this->ensureInitialized();
        $request = Services::request();

        // Handle AJAX actions
        $action = $request->getPost('gc_action') ?? $request->getGet('gc_action');

        if ($action !== null) {
            return $this->handleAjaxAction($action);
        }

        // If trashed view, show trashed records on initial load
        if ($this->trashedView) {
            $this->model->onlyTrashed();
        }

        // Render the list view
        $listData = $this->buildListData(1, null, null, null, null, [], [], [], $this->trashedView);
        $html = $this->theme->renderList($listData);

        // If it's an AJAX request, return JSON with HTML
        if ($request->isAJAX()) {
            return Services::response()
                ->setContentType('application/json')
                ->setJSON([
                    'success' => true,
                    'html'    => $html,
                ]);
        }

        // Full page render
        return $this->renderer->renderPage($this->theme, $listData);
    }

    /**
     * Get list data for AJAX loading.
     */
    public function ajaxList(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();

        $page = (int) ($request->getGet('page') ?? $request->getPost('page') ?? 1);
        $search = $request->getGet('search') ?? $request->getPost('search') ?? null;
        $perPage = (int) ($request->getGet('perPage') ?? $request->getPost('perPage') ?? $this->perPage);
        $sortField = $request->getGet('sort_field') ?? $request->getPost('sort_field') ?? null;
        $sortDir = $request->getGet('sort_dir') ?? $request->getPost('sort_dir') ?? null;
        $filtersJson = $request->getGet('filters') ?? $request->getPost('filters') ?? '{}';
        $filters = json_decode($filtersJson, true) ?? [];
        $advancedFilters = json_decode($request->getGet('advanced_filters') ?? '[]', true) ?: [];

        $listData = $this->buildListData(max(1, $page), $search, $perPage, $sortField, $sortDir, $filters, $advancedFilters, $this->resolveColumns());

        return Services::response()
            ->setContentType('application/json')
            ->setJSON([
                'success'       => true,
                'html'          => $this->theme->renderList($listData),
                'totalCount'    => $listData['totalCount'],
                'currentPage'   => $listData['currentPage'],
                'perPage'       => $listData['perPage'],
            ]);
    }

    /**
     * Get add form.
     */
    public function ajaxAddForm(): ResponseInterface
    {
        $this->ensureInitialized();
        $data = $this->buildFormData('add');

        return Services::response()
            ->setContentType('application/json')
            ->setJSON([
                'success' => true,
                'html'    => $this->theme->renderAddForm($data),
            ]);
    }

    /**
     * Process add form submission.
     */
    public function ajaxAdd(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();
        $data = $request->getPost();

        // Remove action key
        unset($data['gc_action']);

        // Validation
        $errors = $this->validationManager->validate($data);
        if (!empty($errors)) {
            return $this->jsonResponse(false, [
                'errors' => $errors,
                'message' => $this->getLang('insert_fail') ?? 'Validation failed.',
            ]);
        }

        try {
            // Before insert callback
            $data = $this->callbackManager->executeBefore('beforeInsert', $data);

            // Handle uploads
            $data = $this->handleUploads($data);
            if ($data === false) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('upload_error') ?? 'Upload failed.',
                ]);
            }

            // Process repeater fields (JSON encode / unset hasMany)
            $this->processRepeaterDataBeforeSave($data);

            // Remove N-to-N fields (virtual, not actual columns) before insert
            $data = $this->stripNtoNFields($data);

            // Insert
            $insertId = $this->model->insert($data);

            if ($insertId === false || $insertId === 0) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('insert_fail') ?? 'Failed to insert record.',
                ]);
            }

            // Handle repeater hasMany data
            $this->processRepeaterDataAfterSave($insertId);

            // Handle NtoN relations
            $this->handleNtoNInsert($insertId, $request->getPost());

            // After insert callback
            $this->callbackManager->executeAfter('afterInsert', [
                'table'         => $this->table,
                'primaryKey'    => $this->primaryKey,
                'insertId'      => $insertId,
                'data'          => $request->getPost(),
            ]);

            return $this->jsonResponse(true, [
                'message'  => $this->getLang('insert_success') ?? 'Record inserted successfully.',
                'insertId' => $insertId,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get edit form.
     */
    public function ajaxEditForm(mixed $id): ResponseInterface
    {
        $this->ensureInitialized();
        $data = $this->buildFormData('edit', $id);

        if ($data === null) {
            return $this->jsonResponse(false, ['message' => 'Record not found.']);
        }

        return Services::response()
            ->setContentType('application/json')
            ->setJSON([
                'success' => true,
                'html'    => $this->theme->renderEditForm($data),
            ]);
    }

    /**
     * Process edit form submission.
     */
    public function ajaxEdit(mixed $id): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();
        $data = $request->getPost();

        unset($data['gc_action'], $data[$this->primaryKey]);

        // Validation (unique ignore current record)
        foreach ($this->uniqueFields as $uniqueField) {
            if (isset($data[$uniqueField])) {
                $this->validationManager->uniqueExcept($uniqueField, $id, $this->columnLabels[$uniqueField] ?? null);
            }
        }

        $errors = $this->validationManager->validate($data);
        if (!empty($errors)) {
            return $this->jsonResponse(false, [
                'errors'  => $errors,
                'message' => $this->getLang('update_fail') ?? 'Validation failed.',
            ]);
        }

        try {
            // Before update callback
            $data = $this->callbackManager->executeBefore('beforeUpdate', $data);

            // Handle uploads
            $data = $this->handleUploads($data, $id);
            if ($data === false) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('upload_error') ?? 'Upload failed.',
                ]);
            }

            // Preserve existing file if no new upload
            foreach ($this->uploadFieldConfigs as $field => $config) {
                if (!isset($data[$field]) && $request->getPost($field . '_existing')) {
                    $data[$field] = $request->getPost($field . '_existing');
                }
            }

            // Strip _existing keys from data (hidden inputs, not real columns)
            foreach (array_keys($data) as $key) {
                if (str_ends_with($key, '_existing')) {
                    unset($data[$key]);
                }
            }

            // Process repeater fields (JSON encode / unset hasMany)
            $this->processRepeaterDataBeforeSave($data);

            // Remove N-to-N fields (virtual, not actual columns) before update
            $data = $this->stripNtoNFields($data);

            // Update
            $updated = $this->model->update($id, $data);

            // Handle repeater hasMany data
            $this->processRepeaterDataAfterSave($id);

            if (!$updated) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('update_fail') ?? 'Failed to update record.',
                ]);
            }

            // Handle NtoN relations
            $this->handleNtoNUpdate($id, $request->getPost());

            // After update callback
            $this->callbackManager->executeAfter('afterUpdate', [
                'table'      => $this->table,
                'primaryKey' => $this->primaryKey,
                'id'         => $id,
                'data'       => $request->getPost(),
            ]);

            return $this->jsonResponse(true, [
                'message' => $this->getLang('update_success') ?? 'Record updated successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle inline save (inline editing).
     *
     * Expects POST: id, field, value
     */
    private function ajaxInlineSave(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();

        $id    = $request->getPost('id');
        $field = $request->getPost('field');
        $value = $request->getPost('value');

        if (empty($id) || empty($field)) {
            return $this->jsonResponse(false, ['message' => 'Missing parameters.']);
        }

        // Check if inline editing is enabled
        if (!$this->enableInlineEditing) {
            return $this->jsonResponse(false, ['message' => 'Inline editing is disabled.']);
        }

        // Check if column is allowed for inline editing
        if (!empty($this->inlineEditColumns) && !in_array($field, $this->inlineEditColumns, true)) {
            return $this->jsonResponse(false, ['message' => 'Column is not editable.']);
        }

        // Validate only the field being edited (other fields not submitted)
        // Pass record ID so is_unique excludes current record
        $errors = $this->validationManager->validateField($field, $value, $id);
        if (!empty($errors)) {
            return $this->jsonResponse(false, [
                'errors'  => $errors,
                'message' => $this->getLang('update_fail') ?? 'Validation failed.',
            ]);
        }

        try {
            $data = [$field => $value];

            // Before update callback
            $data = $this->callbackManager->executeBefore('beforeUpdate', $data);

            // Update
            $updated = $this->model->update($id, $data);

            if (!$updated) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('update_fail') ?? 'Failed to update record.',
                ]);
            }

            // After update callback
            $this->callbackManager->executeAfter('afterUpdate', [
                'table'      => $this->table,
                'primaryKey' => $this->primaryKey,
                'id'         => $id,
                'data'       => [$field => $value],
            ]);

            // Fetch updated record with relations resolved
            $record = $this->model->getRow($id);

            // Get display value (with relation labels)
            $displayValue = $record[$field] ?? $value;

            // Apply column callbacks
            $columnCallbacks = $this->callbackManager->getColumnCallbacks();
            if (isset($columnCallbacks[$field])) {
                $displayValue = $columnCallbacks[$field]($displayValue, $record);
            }

            // If display value is the same as raw value, check fieldOptions for label mapping
            if ($displayValue === $value || $displayValue === null) {
                $fieldTypeOverrides = $this->fieldTypeOverrides[$field] ?? [];
                if (!empty($fieldTypeOverrides['options'][$value])) {
                    $displayValue = $fieldTypeOverrides['options'][$value];
                }
            }

            return $this->jsonResponse(true, [
                'message' => $this->getLang('update_success') ?? 'Record updated successfully.',
                'value'   => $displayValue,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete a record.
     */
    public function ajaxDelete(mixed $id): ResponseInterface
    {
        $this->ensureInitialized();

        try {
            // Before delete callback
            $this->callbackManager->executeBefore('beforeDelete', [
                'table'      => $this->table,
                'primaryKey' => $this->primaryKey,
                'id'         => $id,
            ]);

            // Delete related NtoN records
            $this->handleNtoNDelete($id);

            $deleted = $this->model->delete($id);

            if (!$deleted) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('delete_fail') ?? 'Failed to delete record.',
                ]);
            }

            // After delete callback
            $this->callbackManager->executeAfter('afterDelete', [
                'table'      => $this->table,
                'primaryKey' => $this->primaryKey,
                'id'         => $id,
            ]);

            return $this->jsonResponse(true, [
                'message' => $this->getLang('delete_success') ?? 'Record deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Restore a soft-deleted record.
     */
    public function ajaxRestore(mixed $id): ResponseInterface
    {
        $this->ensureInitialized();

        try {
            $restored = $this->model->restore($id);

            if (!$restored) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('restore_fail') ?? 'Failed to restore record.',
                ]);
            }

            return $this->jsonResponse(true, [
                'message' => $this->getLang('restore_success') ?? 'Record restored successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get list of trashed (soft-deleted) records.
     */
    public function ajaxTrashList(): ResponseInterface
    {
        $this->ensureInitialized();

        $request = Services::request();
        $page = (int) ($request->getGet('page') ?? $request->getPost('page') ?? 1);
        $search = $request->getGet('search') ?? $request->getPost('search') ?? null;
        $perPage = (int) ($request->getGet('perPage') ?? $request->getPost('perPage') ?? $this->perPage);
        $sortField = $request->getGet('sort_field') ?? $request->getPost('sort_field') ?? null;
        $sortDir = $request->getGet('sort_dir') ?? $request->getPost('sort_dir') ?? null;
        $filtersJson = $request->getGet('filters') ?? $request->getPost('filters') ?? '{}';
        $filters = json_decode($filtersJson, true) ?? [];

        // Show only trashed records
        $this->model->onlyTrashed();

        $listData = $this->buildListData(max(1, $page), $search, $perPage, $sortField, $sortDir, $filters, [], $this->resolveColumns(), true);

        return Services::response()
            ->setContentType('application/json')
            ->setJSON([
                'success'       => true,
                'html'          => $this->theme->renderList($listData),
                'totalCount'    => $listData['totalCount'],
                'currentPage'   => $listData['currentPage'],
                'perPage'       => $listData['perPage'],
            ]);
    }

    /**
     * Get sub-grid data for a parent record.
     */
    public function ajaxSubGrid(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();
        $subGridField = $request->getGet('sub_grid') ?? $request->getPost('sub_grid');
        $parentId = $request->getGet('parent_id') ?? $request->getPost('parent_id');

        if ($subGridField === null || $parentId === null) {
            return $this->jsonResponse(false, ['message' => 'Missing sub-grid parameters.']);
        }

        $config = $this->subGrids[$subGridField] ?? null;
        if ($config === null) {
            return $this->jsonResponse(false, ['message' => 'Sub-grid not found.']);
        }

        $records = $this->model->getSubGridData($subGridField, $parentId);

        // Render sub-grid HTML
        $html = $this->theme->renderSubGrid($config, $records);

        return Services::response()
            ->setContentType('application/json')
            ->setJSON([
                'success' => true,
                'html'    => $html,
            ]);
    }

    /**
     * Print View - returns clean printable HTML page.
     */
    public function ajaxPrintView(): ResponseInterface
    {
        $this->ensureInitialized();

        $columns = $this->resolveColumns();

        $records = $this->model->getList(
            $columns,
            0, // no limit
            0,
            $this->orderBy,
            $this->where
        );

        $totalCount = $this->model->getTotalCount($this->where);

        $subject = $this->subject;

        ob_start();
        $exportFormat = 'print';
        include __DIR__ . '/Views/print_view.php';
        $html = ob_get_clean();

        return Services::response()
            ->setContentType('text/html; charset=utf-8')
            ->setBody($html);
    }

    /**
     * Export data.
     */
    public function ajaxExport(string $format): ResponseInterface
    {
        $this->ensureInitialized();

        $columns = $this->resolveColumns();

        $records = $this->model->getList(
            $columns,
            0, // no limit
            0,
            $this->orderBy,
            $this->where
        );

        // Note: column callbacks NOT applied to export (raw data only)
        if ($format === 'csv') {
            $exporter = new CsvExport();
            $content = $exporter->export($records, $this->columnLabels, $columns);
            $filename = $exporter->getFilename($this->table);

            return Services::response()
                ->setContentType($exporter->getContentType())
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody($content);
        }

        if ($format === 'excel') {
            $exporter = new ExcelExport();
            $content = $exporter->export($records, $this->columnLabels, $columns);
            $filename = $exporter->getFilename($this->table);

            return Services::response()
                ->setContentType($exporter->getContentType())
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody($content);
        }

        // PDF
        if ($format === 'pdf') {
            $exporter = new PdfExport();
            $content = $exporter->export($records, $this->columnLabels, $columns, $this->subject);
            $filename = $exporter->getFilename($this->table);

            return Services::response()
                ->setContentType($exporter->getContentType())
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody($content);
        }

        // Unknown format
        return $this->jsonResponse(false, ['message' => 'Unknown export format.']);
    }

    // ======== Internal Methods ========

    /**
     * Ensure the table is set before operations.
     */
    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            throw GroceryCrudException::tableNotSet();
        }
    }

    /**
     * Build the data array for list rendering.
     */
    private function buildListData(int $page = 1, ?string $search = null, ?int $perPage = null, ?string $sortField = null, ?string $sortDir = null, array $filters = [], array $advancedFilters = [], array $columns = [], bool $trashedView = false): array
    {
        // Filter actions and features based on permissions
        $allowedActions = ['add', 'edit', 'delete'];
        foreach ($allowedActions as $action) {
            if (!$this->hasPermission($action)) {
                $this->actions = array_filter($this->actions, fn($a) => $a !== $action);
            }
        }

        // Filter batch actions based on permissions
        if (!$this->hasPermission('delete')) {
            $this->batchActions = array_filter($this->batchActions, fn($label, $id) => !in_array($id, ['delete_selected', 'restore_selected'], true), ARRAY_FILTER_USE_BOTH);
        }

        // Disable export if not permitted
        if (!$this->hasPermission('export')) {
            $this->enableExport = false;
        }

        $perPage = $perPage ?? $this->perPage;
        $offset = ($page - 1) * $perPage;
        if (empty($columns)) {
            $columns = $this->resolveColumns();
        }

        $searchableColumns = $this->searchable ? $columns : [];

        // Merge request sort with default orderBy (request sort takes priority)
        $orders = $this->orderBy;
        if ($sortField !== null && $sortField !== '') {
            $direction = strtoupper($sortDir ?? 'ASC');
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                $direction = 'ASC';
            }
            array_unshift($orders, ['field' => $sortField, 'direction' => $direction]);
        }

        // Set filter types so model knows LIKE vs exact match
        $filterTypes = [];
        foreach ($this->columnFilters as $field => $config) {
            $filterTypes[$field] = $config['type'] ?? 'dropdown';
        }
        $this->model->setFilterTypes($filterTypes);

        // Apply advanced filters
        if (!empty($advancedFilters)) {
            foreach ($advancedFilters as $filter) {
                if (!empty($filter['field']) && !empty($filter['value'])) {
                    $this->model->addAdvancedFilter($filter['field'], $filter['operator'] ?? 'contains', $filter['value']);
                }
            }
        }

        $records = $this->model->getList(
            $columns,
            $perPage,
            $offset,
            $orders,
            $this->where,
            $search,
            $searchableColumns,
            $filters
        );

        $totalCount = $this->model->getTotalCount(
            $this->where,
            $search,
            $searchableColumns,
            $filters
        );

        // Apply column callbacks
        $columnCallbacks = $this->callbackManager->getColumnCallbacks();
        foreach ($records as &$row) {
            // Ensure _raw exists (populated by model's getList)
            if (!isset($row['_raw'])) {
                $row['_raw'] = [];
                foreach ($columns as $col) {
                    $row['_raw'][$col] = $row[$col] ?? '';
                }
            }
            foreach ($columns as $col) {
                if (isset($columnCallbacks[$col])) {
                    $row[$col] = $columnCallbacks[$col]($row[$col] ?? '', $row);
                }
            }
        }

        // Fetch relation options for column filters
        $mergedFilters = $this->columnFilters;
        foreach ($this->columnFilterRelations as $field => $rel) {
            $options = $this->fetchRelationOptions(
                $rel['table'],
                $rel['labelField'],
                $rel['keyField'],
                $rel['where'],
                $rel['order']
            );
            $mergedFilters[$field] = [
                'type'    => 'dropdown',
                'options' => $options,
            ];
        }

        // Build field options for columns (dropdown labels, etc.)
        $fieldOptions = [];
        foreach ($columns as $col) {
            if (isset($this->fieldTypeOverrides[$col]) && !empty($this->fieldTypeOverrides[$col]['options'])) {
                $fieldOptions[$col] = $this->fieldTypeOverrides[$col]['options'];
            }
        }

        // Build inline editing field info
        $inlineEditFieldTypes = [];
        $inlineFieldInfo = [];
        if ($this->enableInlineEditing) {
            // Determine which columns are inline-editable
            $editableColumns = !empty($this->inlineEditColumns)
                ? array_intersect($columns, $this->inlineEditColumns)
                : $columns;

            foreach ($editableColumns as $col) {
                $info = $this->getInlineFieldInfo($col);
                $inlineEditFieldTypes[$col] = $info['type'];
                if (!empty($info['options'])) {
                    $inlineFieldInfo[$col] = $info['options'];
                    // Also ensure fieldOptions has these for display mapping
                    if (!isset($fieldOptions[$col])) {
                        $fieldOptions[$col] = $info['options'];
                    }
                }
            }
        }

        return $this->renderer->prepareListData([
            'columns'              => $columns,
            'columnLabels'         => $this->columnLabels,
            'records'              => $records,
            'totalCount'           => $totalCount,
            'perPage'              => $perPage,
            'currentPage'          => $page,
            'subject'              => $this->subject,
            'primaryKey'           => $this->primaryKey,
            'actions'              => $this->actions,
            'customActions'        => $this->customActions,
            'searchable'           => $this->searchable,
            'enableExport'         => $this->enableExport,
            'exportFormats'        => $this->buildExportFormats(),
            'crudId'               => $this->crudId,
            'sortField'            => $sortField,
            'sortDir'              => $sortDir,
            'columnFilters'        => $mergedFilters,
            'currentFilters'       => $filters,
            'advancedFilters'      => $advancedFilters,
            'batchActions'         => $this->filterBatchActions($trashedView),
            'enableFilters'        => $this->enableFilters,
            'enableColumns'        => $this->enableColumns,
            'enableSettings'       => $this->enableSettings,
            'softDelete'           => $this->softDelete,
            'trashedView'          => $trashedView,
            'subGrids'             => $this->subGrids,
            'fieldOptions'         => $fieldOptions,
            'enableInlineEditing'  => $this->enableInlineEditing && !$trashedView,
            'inlineEditFieldTypes' => $inlineEditFieldTypes,
            'inlineFieldInfo'      => $inlineFieldInfo,
        ]);
    }

    /**
     * Fetch options from a related table for column filter dropdowns.
     *
     * @return array<string, string> key => label pairs
     */
    private function fetchRelationOptions(string $table, string $labelField, string $keyField, ?string $where = null, ?string $order = null): array
    {
        $builder = $this->db->table($table);
        $builder->select("$keyField, $labelField");

        if ($where !== null && $where !== '') {
            $builder->where($where);
        }

        if ($order !== null && $order !== '') {
            $builder->orderBy($order);
        }

        $results = $builder->get()->getResultArray();
        $options = [];
        foreach ($results as $row) {
            $options[(string) $row[$keyField]] = (string) $row[$labelField];
        }

        return $options;
    }

    /**
     * Build form data for add/edit forms.
     */
    private function buildFormData(string $mode, mixed $id = null): ?array
    {
        $fields = $this->resolveFields($mode);
        $record = null;

        if ($mode === 'edit' && $id !== null) {
            // Use raw row to preserve FK values (e.g., category_id=1, not "Electronics")
            $record = $this->model->getRawRow($id);
            if ($record === null) {
                return null;
            }
        }

        $fieldValues = [];
        $fieldTypes = [];
        $fieldOptions = [];
        $uploadFields = [];
        $repeaterData = [];

        // Resolve repeater values
        foreach ($this->repeaterFields as $rField => $rDef) {
            if (!in_array($rField, $fields, true)) {
                $fields[] = $rField;
            }
            $fieldTypes[$rField] = 'repeater';
            if ($mode === 'edit' && $record !== null) {
                if ($rDef['preset'] === 'json') {
                    $raw = $record[$rField] ?? '[]';
                    $repeaterData[$rField] = !empty($raw) && $raw !== '[]'
                        ? (is_string($raw) ? json_decode($raw, true) ?? [] : $raw)
                        : [];
                } elseif ($rDef['preset'] === 'hasMany') {
                    $repeaterData[$rField] = $this->model->getRelatedRows(
                        $rDef['relatedTable'],
                        $rDef['foreignKey'],
                        $id
                    );
                }
            } else {
                $repeaterData[$rField] = [];
            }
        }

        // Detect field types and values
        foreach ($fields as $field) {
            // Skip repeater fields (already resolved above)
            if (isset($this->repeaterFields[$field])) {
                continue;
            }

            // Type detection
            if (isset($this->fieldTypeOverrides[$field])) {
                $override = $this->fieldTypeOverrides[$field];
                $type = $override['type'];
                if (!empty($override['options'])) {
                    $fieldOptions[$field] = $override['options'];
                }
            } elseif ($this->relationManager->hasRelation($field)) {
                $relType = $this->relationManager->getRelationType($field);
                $type = $relType === 'n_to_n' ? 'set' : 'dropdown';
            } else {
                $dbType = $this->model->getFieldType($field);
                $enumValues = $this->model->getEnumValues($field);
                $detectedType = FieldType::detect($dbType ?? 'text', !empty($enumValues) ? $enumValues : null);
                $type = $detectedType->value;
            }

            $fieldTypes[$field] = $type;

            // Value
            if ($mode === 'edit' && $record !== null) {
                $fieldValues[$field] = $record[$field] ?? '';
            } elseif ($mode === 'add') {
                $fieldValues[$field] = '';
            }

            // Options for dropdowns/relations (skip if already set via setFieldType)
            if (isset($fieldOptions[$field])) {
                // Custom options provided, skip auto-detection
            } elseif ($type === 'dropdown' && $this->relationManager->getRelationType($field) === 'belongs_to') {
                $relData = $this->relationManager->getRelationData($field);
                $options = [];
                foreach ($relData as $item) {
                    $options[$item['id']] = $item['title'];
                }
                $fieldOptions[$field] = $options;
            } elseif (in_array($type, ['enum', 'dropdown'], true)) {
                $enumValues = $this->model->getEnumValues($field);
                if (!empty($enumValues)) {
                    $fieldOptions[$field] = array_combine($enumValues, $enumValues);
                }
            } elseif ($this->relationManager->getRelationType($field) === 'n_to_n') {
                // NtoN data for checklist
                $relData = $this->relationManager->getRelationNtoNData($field);
                $options = [];
                foreach ($relData as $item) {
                    $options[$item['id']] = $item['title'];
                }
                $fieldOptions[$field] = $options;

                // Selected values
                if ($mode === 'edit' && $id !== null) {
                    $fieldValues[$field] = $this->relationManager->getRelationNtoNValues($field, $id);
                }
            }

            // Upload fields
            if (isset($this->uploadFieldConfigs[$field])) {
                $uploadFields[$field] = true;
                $fieldTypes[$field] = 'file';
                if (!empty($fieldValues[$field])) {
                    $fieldValues[$field] = $this->uploadManager->getUploadUrl($field, $fieldValues[$field]);
                }
            }
        }

        // Apply field callbacks
        if ($mode === 'add') {
            $addFieldCallbacks = $this->callbackManager->getAddFieldCallbacks();
            foreach ($addFieldCallbacks as $field => $callback) {
                if (isset($fieldValues[$field])) {
                    $fieldValues[$field] = $callback($fieldValues[$field], $record ?? []);
                }
            }
        } else {
            $editFieldCallbacks = $this->callbackManager->getEditFieldCallbacks();
            foreach ($editFieldCallbacks as $field => $callback) {
                if (isset($fieldValues[$field])) {
                    $fieldValues[$field] = $callback($fieldValues[$field], $record ?? []);
                }
            }
        }

        return [
            'fields'         => $fields,
            'fieldLabels'    => $this->columnLabels,
            'fieldTypes'     => $fieldTypes,
            'fieldValues'    => $fieldValues,
            'fieldOptions'   => $fieldOptions,
            'primaryKey'     => $this->primaryKey,
            'recordId'       => $id,
            'subject'        => $this->subject,
            'errors'         => [],
            'requiredFields' => array_flip($this->requiredFields),
            'readOnlyFields' => $this->readOnlyFields,
            'uploadFields'   => $uploadFields,
            'crudId'         => $this->crudId,
            'repeaterFields' => $this->repeaterFields,
            'repeaterData'   => $repeaterData,
            'dependsOn'      => $this->dependsOn,
        ];
    }

    /**
     * Resolve which columns to display.
     *
     * @return array<int, string>
     */
    private function resolveColumns(): array
    {
        if (!empty($this->columns)) {
            return $this->columns;
        }

        // Default: all columns except primary key
        $allColumns = $this->model->getColumnNames();
        return array_values(array_filter($allColumns, fn($col) => $col !== $this->primaryKey));
    }

    /**
     * Resolve which fields to show in the form.
     *
     * @return array<int, string>
     */
    private function resolveFields(string $mode): array
    {
        if ($mode === 'add' && !empty($this->addFields)) {
            return $this->addFields;
        }
        if ($mode === 'edit' && !empty($this->editFields)) {
            return $this->editFields;
        }
        if (!empty($this->fields)) {
            return $this->fields;
        }

        // Default: exclude primary key from forms
        $allColumns = $this->model->getColumnNames();
        return array_values(array_filter($allColumns, fn($col) => $col !== $this->primaryKey));
    }

    /**
     * Handle file uploads in form data.
     */
    private function handleUploads(array $data, mixed $existingId = null): array|false
    {
        $request = Services::request();

        foreach ($this->uploadFieldConfigs as $field => $config) {
            $file = $request->getFile($field);
            if ($file !== null && $file->isValid() && !$file->hasMoved()) {
                try {
                    $filename = $this->uploadManager->processUpload($file, $field);
                    if ($filename !== null) {
                        // Delete old file if updating
                        if ($existingId !== null) {
                            $oldRecord = $this->model->getRow($existingId);
                            $oldFile = $oldRecord[$field] ?? null;
                            if ($oldFile) {
                                $this->uploadManager->deleteFile($oldFile, $field);
                            }
                        }
                        $data[$field] = $filename;
                    }
                } catch (GroceryCrudException $e) {
                    return false;
                }
            } else {
                // Keep existing file if no new upload
                $existing = $request->getPost($field . '_existing');
                if ($existing) {
                    // Safety: if old data stored full URL, extract filename only
                    if (str_contains($existing, '://')) {
                        $existing = basename($existing);
                    }
                    $data[$field] = $existing;
                }
            }
        }

        return $data;
    }

    /**
     * Handle NtoN relation data on insert.
     */
    /**
     * Handle NtoN relation data on insert.
     */
    private function handleNtoNInsert(mixed $insertId, array $postData): void
    {
        foreach ($this->relationManager->getRelationNtoN() as $field => $rel) {
            if (!isset($postData[$field]) || !is_array($postData[$field])) {
                continue;
            }

            $insertBatch = [];
            foreach ($postData[$field] as $targetId) {
                if ($this->isEmptyRelationValue($targetId)) {
                    continue;
                }
                $insertBatch[] = [
                    $rel['primaryKeyInJunction'] => $insertId,
                    $rel['foreignKeyInJunction'] => $targetId,
                ];
            }

            if (!empty($insertBatch)) {
                $this->db->table($rel['junctionTable'])->insertBatch($insertBatch);
            }
        }
    }

    /**
     * Handle NtoN relation data on update.
     */
    private function handleNtoNUpdate(mixed $id, array $postData): void
    {
        foreach ($this->relationManager->getRelationNtoN() as $field => $rel) {
            // Delete existing relations
            $this->db->table($rel['junctionTable'])
                ->where($rel['primaryKeyInJunction'], $id)
                ->delete();

            // Insert new relations
            if (isset($postData[$field]) && is_array($postData[$field])) {
                $insertBatch = [];
                foreach ($postData[$field] as $targetId) {
                    if ($this->isEmptyRelationValue($targetId)) {
                        continue;
                    }
                    $insertBatch[] = [
                        $rel['primaryKeyInJunction'] => $id,
                        $rel['foreignKeyInJunction'] => $targetId,
                    ];
                }
                if (!empty($insertBatch)) {
                    $this->db->table($rel['junctionTable'])->insertBatch($insertBatch);
                }
            }
        }
    }

    /**
     * Check if a relation value should be treated as empty/invalid.
     *
     * @param  mixed $value
     * @return bool
     */
    private function isEmptyRelationValue(mixed $value): bool
    {
        return $value === '' || $value === null || $value === '0' || $value === 0;
    }

    /**
     * Handle NtoN relation data on delete.
     */
    private function handleNtoNDelete(mixed $id): void
    {
        foreach ($this->relationManager->getRelationNtoN() as $field => $rel) {
            $this->db->table($rel['junctionTable'])
                ->where($rel['primaryKeyInJunction'], $id)
                ->delete();
        }
    }

    /**
     * Strip N-to-N relation fields from data array.
     *
     * N-to-N fields (e.g., tags) are virtual — they don't exist as columns
     * in the main table. They must be removed before insert/update to
     * avoid "unknown column" SQL errors.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function stripNtoNFields(array $data): array
    {
        foreach ($this->relationManager->getRelationNtoN() as $field => $rel) {
            unset($data[$field]);
        }
        return $data;
    }

    /**
     * Filter batch actions based on view mode.
     *
     * Active view:  only show delete_selected (hide restore_selected)
     * Trash view:   show both delete_selected and restore_selected
     *
     * @param bool $trashedView
     * @return array<string, string>
     */
    private function filterBatchActions(bool $trashedView): array
    {
        if ($trashedView) {
            // Trash view: show all batch actions
            return $this->batchActions;
        }

        // Active view: show only delete_selected
        return array_intersect_key($this->batchActions, ['delete_selected' => true]);
    }

    /**
     * Map AJAX actions to permission names.
     */
    private function getActionPermission(string $action): string
    {
        return match ($action) {
            'add_form', 'add'                        => 'add',
            'edit_form', 'edit', 'inline_save'       => 'edit',
            'delete', 'batch_action', 'restore'      => 'delete',
            'export', 'print_view'                => 'export',
            'list', 'trash_list', 'sub_grid'          => 'view',
            default                                   => 'view',
        };
    }

    /**
     * Handle AJAX action routing.
     */
    private function handleAjaxAction(string $action): ResponseInterface
    {
        // Check permission for this action
        $requiredPermission = $this->getActionPermission($action);
        if (!$this->hasPermission($requiredPermission)) {
            return $this->jsonResponse(false, ['message' => $this->getLang('permission_denied') ?? 'Permission denied.']);
        }

        return match ($action) {
            'list'          => $this->ajaxList(),
            'add_form'      => $this->ajaxAddForm(),
            'add'           => $this->ajaxAdd(),
            'edit_form'     => $this->ajaxEditForm($this->getRequestId()),
            'edit'          => $this->ajaxEdit($this->getRequestId()),
            'delete'        => $this->ajaxDelete($this->getRequestId()),
            'export'        => $this->ajaxExport($this->getExportFormat()),
            'print_view'    => $this->ajaxPrintView(),
            'batch_action'  => $this->ajaxBatchAction(),
            'restore'       => $this->ajaxRestore($this->getRequestId()),
            'trash_list'    => $this->ajaxTrashList(),
            'sub_grid'      => $this->ajaxSubGrid(),
            'inline_save'   => $this->ajaxInlineSave(),
            default         => $this->jsonResponse(false, ['message' => 'Invalid action.']),
        };
    }

    /**
     * Handle batch actions (e.g. delete selected).
     */
    private function ajaxBatchAction(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();
        $batchAction = $request->getPost('batch_action') ?? $request->getGet('batch_action');
        $ids = $request->getPost('ids') ?? $request->getGet('ids') ?? [];

        if (empty($batchAction) || !isset($this->batchActions[$batchAction])) {
            return $this->jsonResponse(false, ['message' => 'Invalid batch action.']);
        }

        if (!is_array($ids) || empty($ids)) {
            return $this->jsonResponse(false, ['message' => 'No records selected.']);
        }

        $permanentDelete = (bool) ($request->getPost('permanent_delete') ?? $request->getGet('permanent_delete') ?? false);

        try {
            $result = match ($batchAction) {
                'delete_selected' => $permanentDelete
                    ? $this->model->forceDeleteMultiple($ids)
                    : $this->model->deleteMultiple($ids),
                'restore_selected' => $this->model->restoreMultiple($ids),
                default => false,
            };

            if ($result) {
                return $this->jsonResponse(true, [
                    'message' => $this->getLang('batch_success') ?? 'Batch action completed.',
                ]);
            }

            return $this->jsonResponse(false, [
                'message' => $this->getLang('batch_fail') ?? 'Batch action failed.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get request ID from POST or GET.
     */
    private function getRequestId(): mixed
    {
        $request = Services::request();
        $id = $request->getPost($this->primaryKey)
            ?? $request->getGet($this->primaryKey)
            ?? $request->getPost('id')
            ?? $request->getGet('id');

        return $id;
    }

    /**
     * Get export format from request.
     */
    private function getExportFormat(): string
    {
        $request = Services::request();
        return $request->getPost('format') ?? $request->getGet('format') ?? 'csv';
    }

    /**
     * Build the list of available export formats.
     *
     * @return array<int, string>
     */
    private function buildExportFormats(): array
    {
        $formats = $this->config->exportFormats;

        if ($this->enablePrintView && !in_array('print', $formats, true)) {
            $formats[] = 'print';
        }

        if ($this->enablePdfExport && !in_array('pdf', $formats, true)) {
            $formats[] = 'pdf';
        }

        return $formats;
    }

    /**
     * Get a language string.
     */
    private function getLang(string $key): string
    {
        return $this->languageStrings[$key] ?? $key;
    }

    /**
     * Create a JSON response.
     */
    private function jsonResponse(bool $success, array $data = []): ResponseInterface
    {
        $response = Services::response();
        return $response
            ->setContentType('application/json')
            ->setJSON(array_merge(['success' => $success], $data));
    }

    // ======== Repeater Field Helpers ========

    /**
     * Process repeater data before save.
     * For JSON preset: json_encode the array.
     * For HasMany preset: unset from data (handled separately).
     */
    private function processRepeaterDataBeforeSave(array &$data): void
    {
        foreach ($this->repeaterFields as $field => $rDef) {
            if (!isset($data[$field])) {
                continue;
            }
            if ($rDef['preset'] === 'json') {
                $data[$field] = json_encode(array_values($data[$field]));
            } elseif ($rDef['preset'] === 'hasMany') {
                // Store temporarily for later processing
                $this->repeaterUnsaved[$field] = $data[$field];
                unset($data[$field]);
            }
        }
    }

    /** @var array<string, array> Temporary storage for hasMany repeater data during save */
    private array $repeaterUnsaved = [];

    /**
     * Save hasMany repeater data after the main record is saved.
     */
    private function saveRepeaterHasMany(string $field, mixed $parentId): void
    {
        $rDef = $this->repeaterFields[$field] ?? null;
        $items = $this->repeaterUnsaved[$field] ?? [];
        if ($rDef === null || empty($items)) {
            return;
        }

        $relatedTable = $rDef['relatedTable'];
        $foreignKey = $rDef['foreignKey'];
        $relatedKey = $rDef['relatedKey'] ?? 'id';

        // Delete existing related rows
        $this->model->deleteRelatedRows($relatedTable, $foreignKey, $parentId);

        // Insert new rows
        foreach ($items as $item) {
            $item[$foreignKey] = $parentId;
            $this->model->insertRelatedRow($relatedTable, $item);
        }
    }

    /**
     * Process repeater data after the main record is saved.
     */
    private function processRepeaterDataAfterSave(mixed $recordId): void
    {
        foreach ($this->repeaterFields as $field => $rDef) {
            if ($rDef['preset'] === 'hasMany') {
                $this->saveRepeaterHasMany($field, $recordId);
            }
        }
    }
}
