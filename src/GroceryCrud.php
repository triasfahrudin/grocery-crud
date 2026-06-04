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
    private bool $initialized = false;

    /** @var array<string, array<string, mixed>> */
    private array $uploadFieldConfigs = [];

    /** @var array<string, array<int, string>> */
    private array $enumCache = [];

    /** @var array<string, string> */
    private array $fieldTypeOverrides = [];

    /** @var array<int, string> */
    private array $requiredFields = [];

    /** @var array<int, string> */
    private array $readOnlyFields = [];

    /** @var array<int, string> */
    private array $uniqueFields = [];

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
    public function setFieldType(string $field, string $type): self
    {
        $this->fieldTypeOverrides[$field] = $type;
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

        // Render the list view
        $listData = $this->buildListData(1);
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

        $listData = $this->buildListData(max(1, $page), $search, $perPage);

        return Services::response()
            ->setContentType('application/json')
            ->setJSON([
                'success'     => true,
                'html'        => $this->theme->renderList($listData),
                'totalCount'  => $listData['totalCount'],
                'currentPage' => $listData['currentPage'],
                'perPage'     => $listData['perPage'],
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

            // Remove N-to-N fields (virtual, not actual columns) before insert
            $data = $this->stripNtoNFields($data);

            // Insert
            $insertId = $this->model->insert($data);

            if ($insertId === false || $insertId === 0) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('insert_fail') ?? 'Failed to insert record.',
                ]);
            }

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

            // Remove N-to-N fields (virtual, not actual columns) before update
            $data = $this->stripNtoNFields($data);

            // Update
            $updated = $this->model->update($id, $data);

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

        // Excel
        $exporter = new ExcelExport();
        $content = $exporter->export($records, $this->columnLabels, $columns);
        $filename = $exporter->getFilename($this->table);

        return Services::response()
            ->setContentType($exporter->getContentType())
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content);
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
    private function buildListData(int $page = 1, ?string $search = null, ?int $perPage = null): array
    {
        $perPage = $perPage ?? $this->perPage;
        $offset = ($page - 1) * $perPage;
        $columns = $this->resolveColumns();

        $searchableColumns = $this->searchable ? $columns : [];

        $records = $this->model->getList(
            $columns,
            $perPage,
            $offset,
            $this->orderBy,
            $this->where,
            $search,
            $searchableColumns
        );

        $totalCount = $this->model->getTotalCount(
            $this->where,
            $search,
            $searchableColumns
        );

        // Apply column callbacks
        $columnCallbacks = $this->callbackManager->getColumnCallbacks();
        foreach ($records as &$row) {
            foreach ($columns as $col) {
                if (isset($columnCallbacks[$col])) {
                    $row[$col] = $columnCallbacks[$col]($row[$col] ?? '', $row);
                }
            }
        }

        return $this->renderer->prepareListData([
            'columns'       => $columns,
            'columnLabels'  => $this->columnLabels,
            'records'       => $records,
            'totalCount'    => $totalCount,
            'perPage'       => $perPage,
            'currentPage'   => $page,
            'subject'       => $this->subject,
            'primaryKey'    => $this->primaryKey,
            'actions'       => $this->actions,
            'customActions' => $this->customActions,
            'searchable'    => $this->searchable,
            'enableExport'  => $this->enableExport,
            'exportFormats' => $this->config->exportFormats,
            'crudId'        => $this->crudId,
        ]);
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

        // Detect field types and values
        foreach ($fields as $field) {
            // Type detection
            if (isset($this->fieldTypeOverrides[$field])) {
                $type = $this->fieldTypeOverrides[$field];
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

            // Options for dropdowns/relations
            if ($type === 'dropdown' && $this->relationManager->getRelationType($field) === 'belongs_to') {
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
                if ($request->getPost($field . '_existing')) {
                    $data[$field] = $request->getPost($field . '_existing');
                }
            }
        }

        return $data;
    }

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
     * Handle AJAX action routing.
     */
    private function handleAjaxAction(string $action): ResponseInterface
    {
        return match ($action) {
            'list'      => $this->ajaxList(),
            'add_form'  => $this->ajaxAddForm(),
            'add'       => $this->ajaxAdd(),
            'edit_form' => $this->ajaxEditForm($this->getRequestId()),
            'edit'      => $this->ajaxEdit($this->getRequestId()),
            'delete'    => $this->ajaxDelete($this->getRequestId()),
            'export'    => $this->ajaxExport($this->getExportFormat()),
            default     => $this->jsonResponse(false, ['message' => 'Invalid action.']),
        };
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
}
