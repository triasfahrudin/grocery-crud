<?php

declare(strict_types=1);

namespace GroceryCrud;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Validation\Validation;
use Config\Database;
use Config\Services;
use GroceryCrud\ActivityLog\ActivityLogManager;
use GroceryCrud\Callbacks\CallbackManager;
use GroceryCrud\Config\Config as GCConfig;
use GroceryCrud\Exceptions\GroceryCrudException;
use GroceryCrud\Export\CsvExport;
use GroceryCrud\FileManager\FileManager;
use GroceryCrud\Export\ExcelExport;
use GroceryCrud\Export\PdfExport;
use GroceryCrud\Import\ImportManager;
use GroceryCrud\Lock\RecordLockManager;
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
    private ?ImportManager $importManager = null;
    private ?FileManager $fileManager = null;
    private bool $fileManagerExplicitlyEnabled = false;
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

    /** @var array<int, array<string, mixed>> */
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
    private bool $enableImport;
    private bool $enablePrintView = true;
    private bool $enablePdfExport = true;
    private bool $initialized = false;

    /** @var array<string, array<string, mixed>> */
    private array $uploadFieldConfigs = [];

    /** @var array<string, array<int, string>> */
    private array $enumCache = [];

    /** @var array<string, array{type: string, options?: array}> */
    private array $fieldTypeOverrides = [];

    /** @var array<string, array<string>> Izin berbasis peran: role => [actions] */
    private array $permissions = [];

    /** @var ?callable Callback untuk mendapatkan peran pengguna saat ini: fn(): ?string */
    private $permissionCallback = null;

    /** @var ?string Peran pengguna yang di-cache */
    private ?string $userRole = null;

    /** @var bool Mengaktifkan pengeditan inline pada sel tabel */
    private bool $enableInlineEditing = false;

    /** @var array<int, string> Kolom yang dapat diedit inline (kosong = semua kolom) */
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

    /** @var bool Mengaktifkan soft delete pada model */
    private bool $softDelete = false;

    /** @var bool Apakah kita sedang melihat record yang terhapus (trashed) */
    private bool $trashedView = false;

    /** @var bool Mengaktifkan fitur duplikasi record */
    private bool $enableClone = false;

    /** @var array<int, string> Nama field yang dikecualikan saat duplikasi */
    private array $cloneExcludeFields = [];

    /** @var array<string, string> */
    private array $batchActions = [];

    /** @var array<string, array{label: string, repeatables: array<int, array{name: string, label: string, type: string, rules?: string, options?: array}>, preset: string, foreignKey?: string, relatedTable?: string, relatedKey?: string}> */
    private array $repeaterFields = [];

    /** @var array<string, array{relatedTable: string, foreignKey: string, columns: array, columnLabels: array}> */
    private array $subGrids = [];

    /** @var array<string, array{field: string, value: mixed, action: string}> */
    private array $dependsOn = [];

    /** @var array<string, array{dependsOnField: string, relatedTable: string, foreignKey: string, titleField: string, keyField: string, where: ?string, orderBy: ?string}> */
    private array $dependentRelations = [];

    /** @var array<string, array{displayFields: array<int, string>}> */
    private array $relationPopovers = [];

    /** @var array<int, array{label: string, fields: array<int, string>, type: string}> */
    private array $fieldGroups = [];

    private bool $enableFilters = true;
    private bool $enableColumns = true;
    private bool $enableSettings = true;

    /** @var bool Menampilkan tombol viewer Activity Log di toolbar daftar */
    private bool $enableActivityLogViewer = false;

    /** @var string|null Nama field tanggal/datetime untuk Tampilan Kalender */
    private ?string $calendarField = null;

    /** @var string|null Nama field yang digunakan sebagai judul event di Tampilan Kalender */
    private ?string $calendarTitleField = null;

    private string $crudId;

    /** @var bool Mengaktifkan mode REST API (mengembalikan JSON, bukan HTML) */
    private bool $apiMode = false;

    /** @var ?ActivityLogManager Manajer Activity Log / Audit Trail */
    private ?ActivityLogManager $activityLog = null;

    /** @var ?RecordLockManager Manajer penguncian tingkat record */
    private ?RecordLockManager $recordLockManager = null;

    /** @var ?callable Callback untuk mendapatkan info pengguna saat ini untuk penguncian: fn(): array{id: string, name: string} */
    private $lockUserCallback = null;

    /** @var array<string, callable> Callback aksi kustom: label => fn(mixed $id, array $row): array{success: bool, message: string} */
    private array $actionCallbacks = [];

    /** @var ?callable Callback untuk mendapatkan ID pengguna saat ini: fn(): string */
    private $settingUserIdResolver = null;

    /** @var array<string, mixed>|null Settings dari database (di-cache per request) */
    private ?array $dbSettings = null;

    /** @var int Menit sebelum kunci record kedaluwarsa secara otomatis */
    private int $lockMinutes = 5;

    /** @var string HTML opsional untuk disisipkan di bagian atas halaman (di dalam <body>) */
    private string $headerHtml = '';

    /**
     * Mengatur konten HTML tambahan untuk disisipkan di bagian atas halaman yang dirender,
     * tepat setelah tag <body>. Berguna untuk navigation bar, banner, dll.
     *
     * @param string $html
     * @return $this
     */
    public function setPageHeader(string $html): self
    {
        $this->headerHtml = $html;

        return $this;
    }

    /**
     * Mengaktifkan UI viewer Activity Log bawaan.
     *
     * Memerlukan enableActivityLog() untuk dipanggil terlebih dahulu.
     * Menambahkan tombol "Activity Log" ke toolbar daftar dan merender
     * viewer lengkap dengan filter, paginasi, dan diff detail.
     *
     * @param bool $enable
     * @return $this
     */
    public function enableActivityLogViewer(bool $enable = true): self
    {
        $this->enableActivityLogViewer = $enable;

        return $this;
    }

    /**
     * Mengaktifkan Tampilan Kalender menggunakan FullCalendar.
     *
     * Menambahkan tombol toggle untuk beralih antara tampilan daftar tabel
     * dan tampilan kalender. Record ditampilkan sebagai event berdasarkan
     * field tanggal/datetime yang ditentukan.
     *
     * @param string $dateField   Nama kolom tanggal/datetime untuk tanggal event.
     * @param string|null $titleField Field opsional yang digunakan sebagai judul event.
     *                                Kembali ke primary key jika null.
     * @return $this
     */
    public function setCalendarView(string $dateField, ?string $titleField = null): self
    {
        $this->calendarField = $dateField;
        $this->calendarTitleField = $titleField;

        return $this;
    }

    /**
     * Mengaktifkan penguncian tingkat record untuk mencegah pengeditan bersamaan oleh banyak pengguna.
     *
     * Saat diaktifkan, membuka form edit akan mengunci record tersebut.
     * Pengguna lain akan melihat peringatan jika mereka mencoba mengedit record yang sama.
     * Kunci dilepaskan saat simpan/batal dan kedaluwarsa otomatis setelah $lockMinutes.
     *
     * Memerlukan setLockUserCallback() untuk mengidentifikasi pengguna saat ini.
     *
     * @param int $lockMinutes Waktu kedaluwarsa kunci dalam menit (default: 5)
     * @return $this
     */
    public function enableRecordLocking(int $lockMinutes = 5): self
    {
        $this->lockMinutes = max(1, $lockMinutes);
        $this->recordLockManager = new RecordLockManager(null, $this->lockMinutes);

        return $this;
    }

    /**
     * Mengatur callback untuk mendapatkan identitas pengguna saat ini untuk penguncian record.
     *
     * Callback harus mengembalikan array dengan kunci 'id' dan 'name'.
     * Contoh:
     *   $crud->setLockUserCallback(function() {
     *       return ['id' => (string) auth()->id(), 'name' => auth()->user()->name];
     *   });
     *
     * @param callable $callback fn(): array{id: string, name: string}
     * @return $this
     */
    public function setLockUserCallback(callable $callback): self
    {
        $this->lockUserCallback = $callback;

        return $this;
    }

    /**
     * Mendapatkan info pengguna saat ini untuk penguncian record.
     *
     * @return array{id: string, name: string}
     */
    private function getLockUserInfo(): array
    {
        if ($this->lockUserCallback !== null) {
            $info = call_user_func($this->lockUserCallback);
            if (is_array($info) && isset($info['id'], $info['name'])) {
                return ['id' => (string) $info['id'], 'name' => (string) $info['name']];
            }
        }

        // Fallback: gunakan session ID
        $sessionId = session_id() ?: md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return ['id' => $sessionId, 'name' => 'Anonymous (' . substr($sessionId, 0, 8) . ')'];
    }

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
        $this->enableImport = $this->config->enableImport;
        $this->enablePrintView = $this->config->enablePrintView;
        $this->enablePdfExport = $this->config->enablePdfExport;
        $this->actions = $this->config->defaultActions;
        $this->crudId = 'crud_' . uniqid();

        // Mengatur tema default
        $themeClass = $this->config->themes[$this->config->defaultTheme] ?? Bootstrap5Theme::class;
        $this->theme = new $themeClass();

        // Memuat bahasa default
        $langClass = $this->config->languages[$this->config->defaultLanguage] ?? $this->config->languages['english'];
        if (class_exists($langClass)) {
            $langObj = new $langClass();
            $this->languageStrings = $langObj->strings;
            $this->theme->setLanguageStrings($this->languageStrings);
        }
    }

    // ======== API Konfigurasi Fluent ========

    /**
     * Mengatur tabel utama dan subjek opsional.
     */
    public function setTable(string $table, ?string $subject = null): self
    {
        $this->table = $table;
        $this->subject = $subject ?? ucfirst(str_replace('_', ' ', $table));

        // Inisialisasi model dan relation manager
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
     * Mengatur nama subjek.
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Mengatur kolom yang akan ditampilkan di tabel.
     */
    public function setColumns(...$columns): self
    {
        $this->columns = is_array($columns[0] ?? null) ? $columns[0] : $columns;
        return $this;
    }

    /**
     * Mengatur field untuk form tambah/edit.
     */
    public function setFields(...$fields): self
    {
        $this->fields = is_array($fields[0] ?? null) ? $fields[0] : $fields;
        return $this;
    }

    /**
     * Mengatur field untuk form tambah saja.
     */
    public function setAddFields(...$fields): self
    {
        $this->addFields = is_array($fields[0] ?? null) ? $fields[0] : $fields;
        return $this;
    }

    /**
     * Mengatur field untuk form edit saja.
     */
    public function setEditFields(...$fields): self
    {
        $this->editFields = is_array($fields[0] ?? null) ? $fields[0] : $fields;
        return $this;
    }

    /**
     * Mengatur label tampilan untuk sebuah field/kolom.
     */
    public function displayAs(string $field, string $label): self
    {
        $this->columnLabels[$field] = $label;
        return $this;
    }

    /**
     * Mengatur relasi (belongs_to).
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

        // Mendaftarkan ke model untuk pengambilan nilai tampilan
        $this->model->setRelationField($field, [
            'relatedTable'      => $relatedTable,
            'relatedTitleField' => $relatedTitleField,
            'foreignKey'        => $field,
        ]);

        return $this;
    }

    /**
     * Mengatur relasi N-to-N (many-to-many).
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

        // Mendaftarkan ke model
        $this->model->setRelationNtoN($field, [
            'junctionTable'        => $junctionTable,
            'primaryKeyInJunction' => $primaryKeyInJunction,
            'foreignKeyInJunction' => $foreignKeyInJunction,
            'targetTable'          => $targetTable,
            'targetTitleField'     => $targetTitleField,
        ]);

        return $this;
    }

    // ======== Callback ========

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
     * Callback untuk menampilkan nilai kolom.
     */
    public function callbackColumn(string $field, callable $callback): self
    {
        $this->callbackManager->registerColumnCallback($field, $callback);
        return $this;
    }

    /**
     * Callback untuk field form tambah dan edit.
     */
    public function callbackField(string $field, callable $callback): self
    {
        $this->callbackManager->registerFieldCallback($field, $callback);
        return $this;
    }

    /**
     * Callback untuk field form tambah.
     */
    public function callbackAddField(string $field, callable $callback): self
    {
        $this->callbackManager->registerAddFieldCallback($field, $callback);
        return $this;
    }

    /**
     * Callback untuk field form edit.
     */
    public function callbackEditField(string $field, callable $callback): self
    {
        $this->callbackManager->registerEditFieldCallback($field, $callback);
        return $this;
    }

    // ======== Validasi ========

    /**
     * Mengatur aturan validasi untuk sebuah field.
     */
    public function setRules(string $field, string $rules, ?string $label = null): self
    {
        $this->validationManager->setRules($field, $rules, $label);
        return $this;
    }

    /**
     * Menandai sebuah field sebagai wajib diisi.
     */
    public function required(string $field): self
    {
        $this->validationManager->required($field);
        $this->requiredFields[] = $field;
        return $this;
    }

    /**
     * Menandai sebuah field sebagai unik.
     */
    public function unique(string $field): self
    {
        $this->validationManager->unique($field);
        $this->uniqueFields[] = $field;
        return $this;
    }

    // ======== Unggah ========

    /**
     * Mengatur konfigurasi upload untuk sebuah field.
     */
    public function setUpload(string $field, array $config = []): self
    {
        $this->uploadFieldConfigs[$field] = $config;
        $this->uploadManager->configureField($field, $config);

        // Otomatis aktifkan File Manager jika belum diaktifkan, agar fitur
        // "Pilih dari File Manager" di form upload bisa berfungsi tanpa perlu
        // pengguna memanggil setFileManager() secara manual.
        // Catatan: tombol toolbar File Manager independen hanya muncul jika
        // setFileManager() dipanggil secara eksplisit.
        if ($this->fileManager === null) {
            $this->fileManager = new FileManager($this->config);
        }

        return $this;
    }

    // ======== Tema & Bahasa ========

    /**
     * Mengatur tema rendering.
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
     * Mengatur bahasa.
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
     * Mengatur aksi bawaan mana yang akan ditampilkan.
     */
    public function setActions(string ...$actions): self
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * Menambahkan tombol aksi kustom.
     *
     * @param string $label    Label tombol (teks tooltip)
     * @param string $icon     Kelas ikon Bootstrap Icons (misal: 'bi-check')
     * @param string $url      URL aksi, gunakan {id} sebagai placeholder primary key
     * @param string $cssClass Kelas CSS tambahan untuk tombol
     * @param ?callable $condition Callback opsional: fn(array $row): bool —
     *                              return true untuk menampilkan tombol, false untuk menyembunyikan.
     *                              Menerima data baris saat ini sebagai parameter.
     * @return $this
     */
    public function addAction(string $label, string $icon, string $url, string $cssClass = '', ?callable $condition = null): self
    {
        $action = [
            'label'    => $label,
            'icon'     => $icon,
            'url'      => $url,
            'cssClass' => $cssClass,
        ];

        if ($condition !== null) {
            $action['condition'] = $condition;
        }

        $this->customActions[] = $action;
        return $this;
    }

    /**
     * Mendaftarkan callback handler untuk aksi kustom.
     *
     * Saat tombol aksi kustom diklik, label aksi dikirim via AJAX
     * dan callback yang terdaftar akan dipanggil.
     *
     * Callback menerima (mixed $id, array $row) dan harus mengembalikan
     * array ['success' => bool, 'message' => string].
     *
     * Contoh:
     *   $crud->setActionCallback('Activate', function ($id, $row) {
     *       return ['success' => true, 'message' => 'Product activated.'];
     *   });
     *
     * @param string $label    Label aksi (harus sama dengan yang dipakai di addAction)
     * @param callable $callback fn(mixed $id, array $row): array{success: bool, message: string}
     * @return $this
     */
    public function setActionCallback(string $label, callable $callback): self
    {
        $this->actionCallbacks[$label] = $callback;
        return $this;
    }

    // ======== Pengaturan Tabel per Pengguna (Database) ========

    /**
     * Mengaktifkan penyimpanan pengaturan tabel ke database per pengguna.
     *
     * Pengaturan (urutan kolom, visibilitas, filter) disimpan ke tabel
     * `gc_user_settings` di database, bukan hanya localStorage.
     * Ini memungkinkan pengaturan tetap ada meskipun ganti browser/device.
     *
     * Callback harus mengembalikan string ID unik pengguna (misal: user_id dari session).
     *
     * Contoh:
     *   $crud->setSettingUserId(fn() => (string) session()->get('userId'));
     *
     * @param callable $resolver fn(): string — mengembalikan ID pengguna saat ini
     * @return $this
     */
    public function setSettingUserId(callable $resolver): self
    {
        $this->settingUserIdResolver = $resolver;
        return $this;
    }

    /**
     * Memastikan tabel gc_user_settings ada di database.
     */
    private function ensureSettingsTable(): void
    {
        $this->ensureInitialized();
        $this->db->query("
            CREATE TABLE IF NOT EXISTS gc_user_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(100) NOT NULL,
                table_name VARCHAR(100) NOT NULL,
                settings JSON NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_user_table (user_id, table_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * AJAX: Simpan pengaturan tabel ke database.
     */
    public function ajaxSaveSettings(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->settingUserIdResolver === null) {
            return $this->jsonResponse(false, ['message' => 'Settings user ID not configured.']);
        }

        $userId = call_user_func($this->settingUserIdResolver);
        if (empty($userId)) {
            return $this->jsonResponse(false, ['message' => 'User not authenticated.']);
        }

        $request = Services::request();
        $settings = $request->getPost('settings') ?? '{}';

        try {
            $this->ensureSettingsTable();

            $this->db->query(
                "INSERT INTO gc_user_settings (user_id, table_name, settings)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE settings = VALUES(settings), updated_at = NOW()",
                [$userId, $this->table, $settings]
            );

            return $this->jsonResponse(true, ['message' => 'Settings saved.']);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, ['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Muat pengaturan tabel dari database.
     */
    public function ajaxLoadSettings(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->settingUserIdResolver === null) {
            return $this->jsonResponse(false, ['message' => 'Settings user ID not configured.']);
        }

        $userId = call_user_func($this->settingUserIdResolver);
        if (empty($userId)) {
            return $this->jsonResponse(false, ['message' => 'User not authenticated.']);
        }

        try {
            $this->ensureSettingsTable();

            $row = $this->db->query(
                "SELECT settings FROM gc_user_settings WHERE user_id = ? AND table_name = ?",
                [$userId, $this->table]
            )->getRowArray();

            if ($row === null) {
                return $this->jsonResponse(true, ['settings' => null]);
            }

            return $this->jsonResponse(true, ['settings' => json_decode($row['settings'], true)]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Muat pengaturan dari database (di-cache, untuk inject ke page data).
     */
    private function loadDbSettings(): ?array
    {
        if ($this->dbSettings !== null) {
            return $this->dbSettings;
        }

        if ($this->settingUserIdResolver === null) {
            return null;
        }

        $userId = call_user_func($this->settingUserIdResolver);
        if (empty($userId)) {
            return null;
        }

        try {
            $this->ensureSettingsTable();

            $row = $this->db->query(
                "SELECT settings FROM gc_user_settings WHERE user_id = ? AND table_name = ?",
                [$userId, $this->table]
            )->getRowArray();

            $this->dbSettings = $row ? json_decode($row['settings'], true) : null;
            return $this->dbSettings;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ======== Hapus Lunak (Soft Delete) ========

    /**
     * Mengaktifkan soft delete untuk CRUD.
     *
     * Saat diaktifkan, delete() akan mengisi deleted_at, bukan menghapus permanen.
     * Gunakan withTrashed() untuk melihat/memulihkan record yang dihapus lunak.
     */
    public function setSoftDelete(bool $enabled = true): self
    {
        $this->softDelete = $enabled;
        $this->ensureInitialized();
        $this->model->setSoftDelete($enabled);
        return $this;
    }

    // ======== Duplikasi (Clone) ========

    /**
     * Mengaktifkan fitur duplikasi record.
     *
     * Menambahkan tombol "Duplikat" di kolom aksi pada tampilan daftar.
     * Record asli akan disalin (kecuali primary key dan field yang dikecualikan)
     * dan disimpan sebagai record baru.
     *
     * @param bool $enabled Aktifkan atau nonaktifkan duplikasi
     * @param array<int, string> $excludeFields Nama field yang tidak akan disalin
     * @return $this
     */
    public function setClone(bool $enabled = true, array $excludeFields = []): self
    {
        $this->enableClone = $enabled;
        $this->cloneExcludeFields = $excludeFields;

        // Tambahkan 'clone' ke daftar aksi jika diaktifkan
        if ($enabled && !in_array('clone', $this->actions, true)) {
            $this->actions[] = 'clone';
        }

        return $this;
    }

    /**
     * Menampilkan record termasuk yang di-soft-delete, serta aksi restore.
     * Harus dipanggil sebelum render().
     */
    public function withTrashed(): self
    {
        $this->trashedView = true;
        $this->ensureInitialized();
        $this->model->withTrashed();
        return $this;
    }

    // ======== Konfigurasi Query ========

    /**
     * Mengatur urutan default.
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy[] = ['field' => $field, 'direction' => strtoupper($direction)];
        return $this;
    }

    /**
     * Menambahkan kondisi WHERE.
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
     * Mengatur jumlah item per halaman.
     */
    public function setPerPage(int $perPage): self
    {
        $this->perPage = $perPage > 0 ? $perPage : $this->config->perPage;
        return $this;
    }

    /**
     * Mengaktifkan/menonaktifkan pencarian.
     */
    public function setSearchable(bool $searchable): self
    {
        $this->searchable = $searchable;
        return $this;
    }

    /**
     * Mengaktifkan/menonaktifkan ekspor.
     */
    public function setExportable(bool $exportable): self
    {
        $this->enableExport = $exportable;
        return $this;
    }

    /**
     * Mengaktifkan/menonaktifkan impor.
     */
    public function setImportable(bool $importable = true): self
    {
        $this->enableImport = $importable;
        return $this;
    }

    // ======== File Manager ========

    /**
     * Mengaktifkan File Manager.
     *
     * Menambahkan tombol "File Manager" ke toolbar yang membuka panel
     * pengelola file untuk mengunggah, membuat folder, mengganti nama,
     * menghapus, menyalin, dan memindahkan file.
     *
     * @param array<string, mixed> $config Konfigurasi opsional:
     *   - basePath: Path absolut ke direktori file (default: FCPATH . 'uploads/')
     *   - baseUrl: URL publik untuk mengakses file (default: base_url('uploads'))
     *   - allowedTypes: Tipe file yang diizinkan dipisahkan | (default: '*')
     *   - maxSize: Ukuran maksimum dalam KB (default: 10240 = 10MB)
     * @return $this
     */
    public function setFileManager(array $config = []): self
    {
        // Override konfigurasi default jika ada
        if (!empty($config)) {
            foreach ($config as $key => $value) {
                if (isset($this->config->fileManagerConfig[$key])) {
                    $this->config->fileManagerConfig[$key] = $value;
                }
            }
        }

        $this->fileManager = new FileManager($this->config);
        $this->fileManagerExplicitlyEnabled = true;

        return $this;
    }

    /**
     * Mendapatkan instance FileManager.
     */
    public function getFileManager(): ?FileManager
    {
        return $this->fileManager;
    }

    /**
     * AJAX: Menampilkan daftar file/folder di direktori.
     */
    public function ajaxFileManager(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        $request = Services::request();
        $path = $request->getPost('path') ?? $request->getGet('path') ?? '';
        $view = $request->getPost('view') ?? 'list'; // 'list' atau 'grid'

        try {
            $fmData = $this->fileManager->listFiles($path);
            $tree = $this->fileManager->getDirectoryTree();
            $config = $this->config->fileManagerConfig;

            // Tambahkan data tambahan yang dibutuhkan theme
            $fmData['crudId'] = $this->crudId;
            $fmData['subject'] = $this->subject;
            $fmData['view'] = $view;
            $fmData['allowedTypes'] = $config['allowedTypes'] ?? '*';

            $html = $this->theme->renderFileManager(
                $fmData,
                $tree,
                $config,
                $this->languageStrings
            );

            return $this->jsonResponse(true, [
                'html' => $html,
            ]);
        } catch (GroceryCrudException $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: Mengembalikan HTML daftar file untuk panel file manager.
     */
    public function ajaxFileManagerList(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        $request = Services::request();
        $path = $request->getPost('path') ?? $request->getGet('path') ?? '';
        $view = $request->getPost('view') ?? 'list';

        try {
            $fmData = $this->fileManager->listFiles($path);
            $fmData['view'] = $view;

            $html = $this->theme->renderFileManagerList(
                $fmData,
                $this->languageStrings
            );

            return $this->jsonResponse(true, [
                'html' => $html,
            ]);
        } catch (GroceryCrudException $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: Upload file.
     */
    public function ajaxFileManagerUpload(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        $request = Services::request();
        $path = $request->getPost('path') ?? '';
        $files = $request->getFiles();

        if (empty($files)) {
            return $this->jsonResponse(false, [
                'message' => $this->getLang('file_manager_upload_error') ?? 'No files uploaded.',
            ]);
        }

        $uploaded = 0;
        $errors = [];

        foreach ($files as $file) {
            if (is_array($file)) {
                // Multiple files
                foreach ($file as $singleFile) {
                    try {
                        $this->fileManager->upload($path, $singleFile);
                        $uploaded++;
                    } catch (GroceryCrudException $e) {
                        $errors[] = $e->getMessage();
                    }
                }
            } else {
                try {
                    $this->fileManager->upload($path, $file);
                    $uploaded++;
                } catch (GroceryCrudException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $message = $uploaded > 0
            ? str_replace('{count}', (string) $uploaded, $this->getLang('file_manager_upload_success') ?? '{count} file(s) uploaded successfully.')
            : ($this->getLang('file_manager_upload_error') ?? 'Upload failed.');

        return $this->jsonResponse($uploaded > 0, [
            'message' => $message,
            'uploaded' => $uploaded,
            'errors' => $errors,
        ]);
    }

    /**
     * AJAX: Membuat folder baru.
     */
    public function ajaxFileManagerCreateFolder(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        $request = Services::request();
        $path = $request->getPost('path') ?? '';
        $folderName = $request->getPost('name') ?? '';

        try {
            $this->fileManager->createFolder($path, $folderName);

            return $this->jsonResponse(true, [
                'message' => $this->getLang('file_manager_create_success') ?? 'Folder created successfully.',
            ]);
        } catch (GroceryCrudException $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: Mengganti nama file/folder.
     */
    public function ajaxFileManagerRename(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        $request = Services::request();
        $path = $request->getPost('path') ?? '';
        $newName = $request->getPost('name') ?? '';

        try {
            $this->fileManager->rename($path, $newName);

            return $this->jsonResponse(true, [
                'message' => $this->getLang('file_manager_rename_success') ?? 'Renamed successfully.',
            ]);
        } catch (GroceryCrudException $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: Menghapus file/folder.
     */
    public function ajaxFileManagerDelete(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        $request = Services::request();
        $path = $request->getPost('path') ?? '';

        try {
            $this->fileManager->delete($path);

            return $this->jsonResponse(true, [
                'message' => $this->getLang('file_manager_delete_success') ?? 'Deleted successfully.',
            ]);
        } catch (GroceryCrudException $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: Mendapatkan tree direktori untuk sidebar.
     */
    public function ajaxFileManagerTree(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        try {
            $tree = $this->fileManager->getDirectoryTree();

            $html = $this->theme->renderFolderTree($tree, $this->languageStrings);

            return $this->jsonResponse(true, [
                'html' => $html,
            ]);
        } catch (GroceryCrudException $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: Mencari file.
     */
    public function ajaxFileManagerSearch(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        $request = Services::request();
        $query = $request->getPost('query') ?? $request->getGet('query') ?? '';
        $path = $request->getPost('path') ?? '';

        try {
            $results = $this->fileManager->search($query, $path);

            return $this->jsonResponse(true, [
                'results' => $results,
                'total'   => count($results),
            ]);
        } catch (GroceryCrudException $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: Memindahkan file/folder.
     */
    public function ajaxFileManagerMove(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        $request = Services::request();
        $source = $request->getPost('source') ?? '';
        $destination = $request->getPost('destination') ?? '';

        try {
            $this->fileManager->move($source, $destination);

            return $this->jsonResponse(true, [
                'message' => $this->getLang('file_manager_move_success') ?? 'Moved successfully.',
            ]);
        } catch (GroceryCrudException $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: Menyalin file/folder.
     */
    public function ajaxFileManagerCopy(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        $request = Services::request();
        $source = $request->getPost('source') ?? '';
        $destination = $request->getPost('destination') ?? '';

        try {
            $this->fileManager->copy($source, $destination);

            return $this->jsonResponse(true, [
                'message' => $this->getLang('file_manager_copy_success') ?? 'Copied successfully.',
            ]);
        } catch (GroceryCrudException $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: Mendapatkan info detail file/folder.
     */
    public function ajaxFileManagerFileInfo(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->fileManager === null) {
            return $this->jsonResponse(false, ['message' => 'File manager is not enabled.']);
        }

        $request = Services::request();
        $path = $request->getPost('path') ?? '';

        try {
            $info = $this->fileManager->getFileInfo($path);

            if ($info === null) {
                return $this->jsonResponse(false, [
                    'message' => 'File or folder not found.',
                ]);
            }

            return $this->jsonResponse(true, $info);
        } catch (GroceryCrudException $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mengaktifkan/menonaktifkan tampilan cetak.
     */
    public function setPrintView(bool $enable): self
    {
        $this->enablePrintView = $enable;
        return $this;
    }

    /**
     * Mengaktifkan/menonaktifkan ekspor PDF.
     */
    public function setPdfExport(bool $enable): self
    {
        $this->enablePdfExport = $enable;
        return $this;
    }

    // ======== Izin / RBAC ========

    /**
     * Mengatur aksi yang diizinkan untuk peran tertentu.
     *
     * Aksi yang tersedia: 'add', 'edit', 'delete', 'view', 'export', 'import'
     *
     * Contoh:
     *   $crud->setPermission('admin', ['add', 'edit', 'delete', 'view', 'export', 'import']);
     *   $crud->setPermission('editor', ['add', 'edit', 'view', 'export']);
     *   $crud->setPermission('viewer', ['view', 'export']);
     *
     * @param string   $role    Nama peran (misal 'admin', 'editor', 'viewer')
     * @param string[] $actions Aksi yang diizinkan untuk peran ini
     */
    public function setPermission(string $role, array $actions): self
    {
        $this->permissions[$role] = $actions;
        return $this;
    }

    /**
     * Mengatur callback untuk menentukan peran pengguna saat ini.
     *
     * Callback harus mengembalikan string (nama peran) atau null (tidak terautentikasi).
     * Jika null dikembalikan dan izin ditentukan, semua aksi ditolak.
     *
     * Contoh:
     *   $crud->setPermissionCallback(fn() => session()->get('role'));
     *
     * @param callable $callback fn(): ?string
     */
    public function setPermissionCallback(callable $callback): self
    {
        $this->permissionCallback = $callback;
        return $this;
    }

    // ======== Pengeditan Inline ========

    /**
     * Mengaktifkan atau menonaktifkan pengeditan inline pada tabel.
     *
     * Saat diaktifkan, pengguna dapat klik dua kali pada sel tabel untuk mengedit nilai secara langsung.
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
     * Menentukan kolom mana yang dapat diedit inline.
     *
     * Jika dipanggil tanpa argumen atau array kosong, semua kolom yang terlihat dapat diedit.
     * Kolom relasi/foreign-key dan kolom tanggal akan menampilkan input yang sesuai.
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
     * Menentukan tipe field dan opsi pengeditan inline untuk sebuah kolom.
     *
     * @return array{type: string, options?: array<string, string>}
     */
    private function getInlineFieldInfo(string $field): array
    {
        $this->ensureInitialized();

        // Prioritas: override tipe field
        if (isset($this->fieldTypeOverrides[$field])) {
            $override = $this->fieldTypeOverrides[$field];
            return [
                'type'    => $this->mapFieldTypeForInline($override['type']),
                'options' => $override['options'] ?? [],
            ];
        }

        // Field relasi (belongs_to) => dropdown dengan opsi
        if ($this->relationManager->hasRelation($field) && $this->relationManager->getRelationType($field) === 'belongs_to') {
            $relData = $this->relationManager->getRelationData($field);
            $options = [];
            foreach ($relData as $item) {
                $options[(string) $item['id']] = $item['title'];
            }
            return ['type' => 'select', 'options' => $options];
        }

        // ENUM => dropdown dengan opsi enum
        $enumValues = $this->model->getEnumValues($field);
        if (!empty($enumValues)) {
            $options = array_combine($enumValues, $enumValues);
            return ['type' => 'select', 'options' => $options];
        }

        // Deteksi tipe database
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
     * Memeriksa apakah pengguna saat ini memiliki izin untuk aksi yang diberikan.
     */
    private function hasPermission(string $action): bool
    {
        // Jika tidak ada izin yang ditentukan, izinkan semua
        if (empty($this->permissions) && $this->permissionCallback === null) {
            return true;
        }

        // Resolve peran pengguna dari callback (lazy, sekali)
        if ($this->permissionCallback !== null && $this->userRole === null) {
            $this->userRole = call_user_func($this->permissionCallback);
        }

        // Jika pengguna memiliki peran yang dikenal, periksa izinnya
        if ($this->userRole !== null && isset($this->permissions[$this->userRole])) {
            return in_array($action, $this->permissions[$this->userRole], true);
        }

        // Peran tidak ditemukan di peta izin atau tidak terautentikasi: tolak
        return false;
    }

    /**
     * Mengatur sebuah field sebagai read-only.
     */
    public function setReadOnly(string $field): self
    {
        $this->readOnlyFields[] = $field;
        return $this;
    }

    /**
     * Menimpa tipe field yang terdeteksi otomatis.
     */
    public function setFieldType(string $field, string $type, array $options = []): self
    {
        $this->fieldTypeOverrides[$field] = ['type' => $type, 'options' => $options];
        return $this;
    }

    /**
     * Mendefinisikan kondisi form dinamis: tampilkan/sembunyikan atau aktifkan/nonaktifkan field
     * berdasarkan nilai field lain.
     *
     * Contoh:
     *   $crud->dependsOn('discount_price', 'has_discount', true);
     *   // discount_price ditampilkan hanya ketika has_discount dicentang (true)
     *
     *   $crud->dependsOn('shipping_address', 'same_as_billing', false, 'enable');
     *   // shipping_address dinonaktifkan ketika same_as_billing dicentang (true)
     *
     * @param string $field          Field yang tergantung pada field lain
     * @param string $dependsOnField Nama field pengontrol
     * @param mixed  $value          Nilai yang memicu aksi
     * @param string $action         'show' (sembunyikan jika tidak cocok) atau 'enable' (nonaktifkan jika tidak cocok)
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
     * Mendefinisikan relasi dropdown dependen (cascading).
     *
     * Ketika dropdown induk berubah, opsi dropdown anak akan
     * otomatis dimuat ulang melalui AJAX.
     *
     * Contoh:
     *   $crud->setDependentRelation('sub_category_id', 'category_id', 'sub_categories', 'category_id', 'name');
     *   // opsi sub_category_id difilter berdasarkan nilai category_id
     *
     * @param string $field          Nama field anak (misal 'sub_category_id')
     * @param string $dependsOnField Nama field induk (misal 'category_id')
     * @param string $relatedTable   Tabel terkait untuk anak (misal 'sub_categories')
     * @param string $foreignKey     Kolom FK di tabel terkait (misal 'category_id')
     * @param string $titleField     Kolom judul tampilan di tabel terkait (misal 'name')
     * @param string $keyField       Primary key dari tabel terkait (default: 'id')
     * @param string|null $where     Kondisi WHERE tambahan (string SQL)
     * @param string|null $orderBy   Klausa ORDER BY (string SQL)
     */
    public function setDependentRelation(
        string $field,
        string $dependsOnField,
        string $relatedTable,
        string $foreignKey,
        string $titleField,
        string $keyField = 'id',
        ?string $where = null,
        ?string $orderBy = null
    ): self {
        $this->dependentRelations[$field] = [
            'dependsOnField' => $dependsOnField,
            'relatedTable'   => $relatedTable,
            'foreignKey'     => $foreignKey,
            'titleField'     => $titleField,
            'keyField'       => $keyField,
            'where'          => $where,
            'orderBy'        => $orderBy,
        ];
        return $this;
    }

    /**
     * Mengaktifkan popover relasi untuk sebuah field di tampilan daftar.
     *
     * Saat mengarahkan kursor ke nilai field relasi, tooltip/popover
     * menampilkan detail record terkait yang dimuat melalui AJAX.
     *
     * Contoh:
     *   $crud->setRelation('category_id', 'categories', 'name');
     *   $crud->setRelationPopover('category_id', ['name', 'description', 'created_at']);
     *
     * @param string $field          Nama field relasi
     * @param array<int, string> $displayFields Field dari tabel terkait untuk ditampilkan di popover (default: [])
     */
    public function setRelationPopover(string $field, array $displayFields = []): self
    {
        $this->relationPopovers[$field] = [
            'displayFields' => $displayFields,
        ];
        return $this;
    }

    /**
     * Mengelompokkan field ke dalam tab atau seksi di form tambah/edit.
     *
     * Field yang tidak ditetapkan ke grup mana pun akan muncul di tab "General" default.
     *
     * @param string $label  Label grup (judul tab atau heading seksi)
     * @param array<int, string> $fields Daftar nama field dalam grup ini
     * @param string $type   Tipe grup: 'tab' (default) atau 'section'
     * @return $this
     */
    public function setFieldGroup(string $label, array $fields, string $type = 'tab'): self
    {
        $this->fieldGroups[] = [
            'label'  => $label,
            'fields' => $fields,
            'type'   => $type,
        ];
        return $this;
    }

    /**
     * Menghapus aturan validasi untuk field yang disembunyikan/dinonaktifkan melalui dependsOn
     * ketika nilai field pengontrol tidak sesuai dengan nilai pemicu yang diharapkan.
     *
     * Ini mencegah kesalahan validasi palsu untuk field yang sengaja
     * disembunyikan atau dinonaktifkan di browser sehingga tidak dikirim.
     */
    private function filterDependsOnValidationRules(array $data): void
    {
        foreach ($this->dependsOn as $targetField => $config) {
            $controllerValue = $data[$config['field']] ?? null;

            // Normalisasi: boolean true/false -> '1'/'0' agar sesuai dengan nilai checkbox
            $expectedValue = $config['value'];
            if (is_bool($expectedValue)) {
                $expectedValue = $expectedValue ? '1' : '0';
            }

            if ((string) $controllerValue !== (string) $expectedValue) {
                $this->validationManager->removeRules($targetField);
            }
        }
    }

    /**
     * Menambahkan filter kolom yang merender kontrol filter di header tabel.
     *
     * Tipe yang didukung: 'text', 'dropdown'
     *
     * @param string $field   Nama kolom
     * @param string $type    Tipe filter ('text' atau 'dropdown')
     * @param array  $options Untuk 'dropdown': ['1' => 'Active', '0' => 'Inactive']
     */
    public function setColumnFilter(string $field, string $type, array $options = []): self
    {
        $this->columnFilters[$field] = ['type' => $type, 'options' => $options];
        return $this;
    }

    /**
     * Mengatur filter kolom dengan opsi yang diambil secara dinamis dari tabel terkait.
     *
     * @param string      $field       Nama kolom di tabel saat ini
     * @param string      $table       Nama tabel terkait
     * @param string      $labelField  Field yang ditampilkan sebagai label opsi
     * @param string|null $keyField    Field kunci (default: primary key tabel terkait)
     * @param string|null $where       Kondisi WHERE opsional (misal "status = 'active'")
     * @param string|null $order       ORDER BY opsional (misal "name ASC")
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
     * Mengatur aksi batch.
     *
     * Aksi bawaan: 'delete_selected'
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
     * Alias untuk setBatchAction.
     */
    public function addBatchAction(string $actionId, string $label): self
    {
        return $this->setBatchAction($actionId, $label);
    }

    /**
     * Mendefinisikan field repeater (grup sub-field yang dapat diulang).
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
     * Mendefinisikan sub-grid (tabel CRUD bersarang) yang merender record terkait
     * dalam baris yang dapat diperluas di bawah setiap record induk.
     *
     * @param string $field            Nama field virtual (pengenal, bukan kolom nyata)
     * @param string $relatedTable     Nama tabel terkait
     * @param string $foreignKey       FK di tabel terkait yang mengarah ke induk
     * @param array  $columns          Kolom untuk ditampilkan di sub-grid
     * @param array  $columnLabels     Label kolom opsional
     * @param array  $columnRelations  Pencarian relasi opsional untuk kolom.
     *                                 Format: ['column' => ['relatedTable', 'displayField', 'localKey', 'foreignKey']]
     *                                 Contoh: ['tag_id' => ['tags', 'name', 'tag_id', 'id']]
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
     * Menghapus tombol Filters dari toolbar datagrid.
     */
    public function unsetFilters(): self
    {
        $this->enableFilters = false;
        return $this;
    }

    /**
     * Menghapus tombol Columns dari toolbar datagrid.
     */
    public function unsetColumns(): self
    {
        $this->enableColumns = false;
        return $this;
    }

    /**
     * Menghapus tombol Settings dari toolbar datagrid.
     */
    public function unsetSettings(): self
    {
        $this->enableSettings = false;
        return $this;
    }

    // ======== Mode REST API ========

    /**
     * Mengaktifkan mode REST API.
     *
     * Saat diaktifkan, `render()` mengembalikan respons JSON bersih, bukan HTML.
     * Aksi terdeteksi otomatis dari metode HTTP:
     *   GET    → list (dipaginasi)
     *   POST   → create (insert)
     *   PUT    → update (edit)
     *   DELETE → delete
     *
     * Anda juga dapat melewatkan param query `gc_action` untuk menimpa.
     * Record ID diselesaikan dari `id` atau primary key di parameter GET/POST.
     *
     * @param bool $apiMode
     * @return self
     */
    public function setApiMode(bool $apiMode = true): self
    {
        $this->apiMode = $apiMode;
        return $this;
    }

    // ======== Log Aktivitas / Audit Trail ========

    /**
     * Mengaktifkan Activity Log (Audit Trail).
     *
     * Mencatat otomatis semua operasi CRUD (insert, update, delete, restore,
     * batch) ke tabel activity_logs, termasuk data sebelum & sesudah.
     *
     * @param callable|null $userResolver Callback untuk resolve user current.
     *        Harus return array ['id' => ..., 'name' => ...].
     *        Contoh: function () { return ['id' => user_id(), 'name' => user_name()]; }
     * @return self
     */
    public function enableActivityLog(?callable $userResolver = null): self
    {
        $this->activityLog = new ActivityLogManager($this->db);

        if ($userResolver !== null) {
            $this->activityLog->setUserResolver($userResolver);
        }

        return $this;
    }

    /**
     * Mengatur nama tabel kustom untuk activity logs.
     *
     * Default: 'activity_logs'
     *
     * @param string $tableName
     * @return self
     */
    public function setActivityLogTable(string $tableName): self
    {
        if ($this->activityLog !== null) {
            $this->activityLog->setTableName($tableName);
        }

        return $this;
    }

    /**
     * Mendapatkan instance ActivityLogManager.
     *
     * @return ActivityLogManager|null
     */
    public function getActivityLog(): ?ActivityLogManager
    {
        return $this->activityLog;
    }

    /**
     * Mengatur label field untuk diff yang mudah dibaca di activity logs.
     *
     * @param array<string, string> $labels ['field_name' => 'Label']
     * @return self
     */
    public function setActivityLogFieldLabels(array $labels): self
    {
        if ($this->activityLog !== null) {
            $this->activityLog->setFieldLabels($labels);
        }

        return $this;
    }

    /**
     * Mengatur field yang dikecualikan dari data activity log (misal password).
     *
     * @param array<int, string> $fields
     * @return self
     */
    public function setActivityLogExcludeFields(array $fields): self
    {
        if ($this->activityLog !== null) {
            $this->activityLog->setExcludeFields($fields);
        }

        return $this;
    }

    // ======== Metode Render ========

    /**
     * Merender antarmuka CRUD lengkap (daftar + aksi).
     * Dalam konteks AJAX, mengembalikan JSON; jika tidak, mengembalikan HTML.
     *
     * Dalam mode API, mengembalikan JSON bersih dengan respons gaya REST,
     * mendeteksi aksi secara otomatis dari metode HTTP.
     *
     * @return ResponseInterface|string
     */
    public function render(): ResponseInterface|string
    {
        $this->ensureInitialized();
        $request = Services::request();

        // Tangani aksi AJAX
        $action = $request->getPost('gc_action') ?? $request->getGet('gc_action');

        if ($action !== null) {
            if ($this->apiMode) {
                return $this->handleApiAction($action);
            }
            return $this->handleAjaxAction($action);
        }

        // Mode API: deteksi aksi otomatis dari metode HTTP
        if ($this->apiMode) {
            $method = strtoupper($request->getMethod());
            $hasId = $request->getGet($this->primaryKey) !== null
                  || $request->getGet('id') !== null;

            $action = match ($method) {
                'GET'       => $hasId ? 'read' : 'list',
                'POST'      => 'add',
                'PUT',
                'PATCH'     => 'edit',
                'DELETE'    => 'delete',
                default     => 'list',
            };
            return $this->handleApiAction($action);
        }

        // Jika tampilan trash, tampilkan record yang terhapus pada muatan awal
        if ($this->trashedView) {
            $this->model->onlyTrashed();
        }

        // Render tampilan daftar
        $listData = $this->buildListData(1, null, null, null, null, [], [], [], $this->trashedView);
        $html = $this->theme->renderList($listData);

        // Jika request AJAX, kembalikan JSON dengan HTML
        if ($request->isAJAX()) {
            return Services::response()
                ->setContentType('application/json')
                ->setJSON([
                    'success' => true,
                    'html'    => $html,
                ]);
        }

        // Render halaman penuh
        return $this->renderer->renderPage($this->theme, $listData, $this->headerHtml);
    }

    /**
     * Mendapatkan data daftar untuk pemuatan AJAX.
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
     * Mendapatkan form tambah.
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
     * Memproses pengiriman form tambah.
     */
    public function ajaxAdd(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();
        $data = $request->getPost();

        // Hapus kunci aksi
        unset($data['gc_action']);

        // Lewati validasi untuk field yang dinonaktifkan melalui dependsOn (action='enable')
        $this->filterDependsOnValidationRules($data);

        // Validasi
        $errors = $this->validationManager->validate($data);
        if (!empty($errors)) {
            return $this->jsonResponse(false, [
                'errors' => $errors,
                'message' => $this->getLang('insert_fail') ?? 'Validation failed.',
            ]);
        }

        try {
            // Callback sebelum insert
            $data = $this->callbackManager->executeBefore('beforeInsert', $data);

            // Tangani unggahan
            $data = $this->handleUploads($data);
            if ($data === false) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('upload_error') ?? 'Upload failed.',
                ]);
            }

            // Proses field repeater (JSON encode / unset hasMany)
            $this->processRepeaterDataBeforeSave($data);

            // Hapus field N-to-N (virtual, bukan kolom nyata) sebelum insert
            $data = $this->stripNtoNFields($data);

            // Lakukan insert
            $insertId = $this->model->insert($data);

            if ($insertId === false || $insertId === 0) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('insert_fail') ?? 'Failed to insert record.',
                ]);
            }

            // Tangani data hasMany repeater
            $this->processRepeaterDataAfterSave($insertId);

            // Tangani relasi NtoN
            $this->handleNtoNInsert($insertId, $request->getPost());

            // Callback setelah insert
            $this->callbackManager->executeAfter('afterInsert', [
                'table'         => $this->table,
                'primaryKey'    => $this->primaryKey,
                'insertId'      => $insertId,
                'data'          => $request->getPost(),
            ]);

            // Log Aktivitas
            $this->logActivityInsert($insertId, $data);

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
     * Mendapatkan form edit.
     */
    public function ajaxEditForm(mixed $id): ResponseInterface
    {
        $this->ensureInitialized();

        // Pemeriksaan kunci record
        if ($this->recordLockManager !== null) {
            $userInfo = $this->getLockUserInfo();
            $lockOk = $this->recordLockManager->acquireLock(
                $this->table, $id, $userInfo['id'], $userInfo['name']
            );

            if (!$lockOk) {
                $lock = $this->recordLockManager->getLock($this->table, $id);
                return $this->jsonResponse(false, [
                    'message' => sprintf(
                        $this->getLang('record_locked_by') ?? 'This record is currently being edited by %s.',
                        $lock['user_name'] ?? 'another user'
                    ),
                    'lockInfo' => $lock,
                ]);
            }
        }

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
     * Memproses pengiriman form edit.
     */
    public function ajaxEdit(mixed $id): ResponseInterface
    {
        $this->ensureInitialized();

        // Pemeriksaan kunci record: verifikasi pengguna saat ini memiliki kunci
        if ($this->recordLockManager !== null) {
            $userInfo = $this->getLockUserInfo();
            $lock = $this->recordLockManager->getLock($this->table, $id);
            if ($lock !== null && $lock['user_id'] !== $userInfo['id']) {
                return $this->jsonResponse(false, [
                    'message' => sprintf(
                        $this->getLang('record_locked_by') ?? 'This record is currently being edited by %s.',
                        $lock['user_name'] ?? 'another user'
                    ),
                ]);
            }
        }

        $request = Services::request();
        $data = $request->getPost();

        unset($data['gc_action'], $data[$this->primaryKey]);

        // Validasi (unique abaikan record saat ini)
        foreach ($this->uniqueFields as $uniqueField) {
            if (isset($data[$uniqueField])) {
                $this->validationManager->uniqueExcept($uniqueField, $id, $this->columnLabels[$uniqueField] ?? null);
            }
        }

        // Lewati validasi untuk field yang dinonaktifkan melalui dependsOn (action='enable')
        $this->filterDependsOnValidationRules($data);

        $errors = $this->validationManager->validate($data);
        if (!empty($errors)) {
            return $this->jsonResponse(false, [
                'errors'  => $errors,
                'message' => $this->getLang('update_fail') ?? 'Validation failed.',
            ]);
        }

        try {
            // Callback sebelum update
            $data = $this->callbackManager->executeBefore('beforeUpdate', $data);

            // Ambil data lama untuk activity log sebelum update
            $oldData = $this->activityLog !== null ? $this->model->getRawRow($id) : null;

            // Tangani unggahan
            $data = $this->handleUploads($data, $id);
            if ($data === false) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('upload_error') ?? 'Upload failed.',
                ]);
            }

            // Pertahankan file yang ada jika tidak ada unggahan baru
            foreach ($this->uploadFieldConfigs as $field => $config) {
                if (!isset($data[$field]) && $request->getPost($field . '_existing')) {
                    $data[$field] = $request->getPost($field . '_existing');
                }
            }

            // Hapus kunci _existing dari data (input tersembunyi, bukan kolom nyata)
            foreach (array_keys($data) as $key) {
                if (str_ends_with($key, '_existing')) {
                    unset($data[$key]);
                }
            }

            // Proses field repeater (JSON encode / unset hasMany)
            $this->processRepeaterDataBeforeSave($data);

            // Hapus field N-to-N (virtual, bukan kolom nyata) sebelum update
            $data = $this->stripNtoNFields($data);

            // Lakukan update
            $updated = $this->model->update($id, $data);

            // Tangani data hasMany repeater
            $this->processRepeaterDataAfterSave($id);

            if (!$updated) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('update_fail') ?? 'Failed to update record.',
                ]);
            }

            // Tangani relasi NtoN
            $this->handleNtoNUpdate($id, $request->getPost());

            // Callback setelah update
            $this->callbackManager->executeAfter('afterUpdate', [
                'table'      => $this->table,
                'primaryKey' => $this->primaryKey,
                'id'         => $id,
                'data'       => $request->getPost(),
            ]);

            // Log Aktivitas: log update with old + new data
            $this->logActivityUpdate($id, $oldData ?? [], $data);

            // Lepaskan kunci record
            if ($this->recordLockManager !== null) {
                $this->recordLockManager->releaseLock($this->table, $id);
            }

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
     * Menangani penyimpanan inline (pengeditan inline).
     *
     * Menerima POST: id, field, value
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

        // Periksa apakah pengeditan inline diaktifkan
        if (!$this->enableInlineEditing) {
            return $this->jsonResponse(false, ['message' => 'Inline editing is disabled.']);
        }

        // Periksa apakah kolom diizinkan untuk pengeditan inline
        if (!empty($this->inlineEditColumns) && !in_array($field, $this->inlineEditColumns, true)) {
            return $this->jsonResponse(false, ['message' => 'Column is not editable.']);
        }

        // Validasi hanya field yang diedit (field lain tidak dikirim)
        // Kirim ID record agar is_unique mengecualikan record saat ini
        $errors = $this->validationManager->validateField($field, $value, $id);
        if (!empty($errors)) {
            return $this->jsonResponse(false, [
                'errors'  => $errors,
                'message' => $this->getLang('update_fail') ?? 'Validation failed.',
            ]);
        }

        try {
            $data = [$field => $value];

            // Callback sebelum update
            $data = $this->callbackManager->executeBefore('beforeUpdate', $data);

            // Ambil data lama untuk activity log sebelum update
            $oldData = $this->activityLog !== null ? $this->model->getRawRow($id) : null;

            // Lakukan update
            $updated = $this->model->update($id, $data);

            if (!$updated) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('update_fail') ?? 'Failed to update record.',
                ]);
            }

            // Callback setelah update
            $this->callbackManager->executeAfter('afterUpdate', [
                'table'      => $this->table,
                'primaryKey' => $this->primaryKey,
                'id'         => $id,
                'data'       => [$field => $value],
            ]);

            // Log Aktivitas: log inline update with old + new data
            $this->logActivityUpdate($id, $oldData ?? [], $data);

            // Ambil record yang diperbarui dengan relasi terselesaikan
            $record = $this->model->getRow($id);

            // Dapatkan nilai tampilan (dengan label relasi)
            $displayValue = $record[$field] ?? $value;

            // Terapkan callback kolom
            $columnCallbacks = $this->callbackManager->getColumnCallbacks();
            if (isset($columnCallbacks[$field])) {
                $displayValue = $columnCallbacks[$field]($displayValue, $record);
            }

            // Jika nilai tampilan sama dengan nilai mentah, periksa fieldOptions untuk pemetaan label
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
     * Menghapus sebuah record.
     */
    public function ajaxDelete(mixed $id): ResponseInterface
    {
        $this->ensureInitialized();

        try {
            // Ambil data lama untuk activity log sebelum menghapus
            $oldData = $this->activityLog !== null ? $this->model->getRawRow($id) : null;

            // Callback sebelum hapus
            $this->callbackManager->executeBefore('beforeDelete', [
                'table'      => $this->table,
                'primaryKey' => $this->primaryKey,
                'id'         => $id,
            ]);

            // Hapus record NtoN terkait
            $this->handleNtoNDelete($id);

            $deleted = $this->model->delete($id);

            if (!$deleted) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('delete_fail') ?? 'Failed to delete record.',
                ]);
            }

            // Callback setelah hapus
            $this->callbackManager->executeAfter('afterDelete', [
                'table'      => $this->table,
                'primaryKey' => $this->primaryKey,
                'id'         => $id,
            ]);

            // Log Aktivitas
            $this->logActivityDelete($id, $oldData);

            // Lepaskan kunci record if held
            if ($this->recordLockManager !== null) {
                $this->recordLockManager->releaseLock($this->table, $id);
            }

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
     * Menangani aksi kustom yang diklik dari tombol di daftar.
     *
     * Menerima gc_action=custom_action, action_label (label aksi), dan id (primary key).
     * Memanggil callback yang terdaftar via setActionCallback().
     */
    public function ajaxCustomAction(mixed $id): ResponseInterface
    {
        $this->ensureInitialized();

        $request = Services::request();
        $actionLabel = $request->getPost('action_label') ?? '';

        if ($actionLabel === '' || !isset($this->actionCallbacks[$actionLabel])) {
            return $this->jsonResponse(false, [
                'message' => $this->getLang('invalid_action') ?? 'Invalid action.',
            ]);
        }

        try {
            // Ambil data baris untuk dikirim ke callback
            $row = $this->model->getRawRow($id);

            if ($row === null) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('record_not_found') ?? 'Record not found.',
                ]);
            }

            $result = ($this->actionCallbacks[$actionLabel])($id, $row);

            return $this->jsonResponse(
                $result['success'] ?? false,
                ['message' => $result['message'] ?? 'Action completed.']
            );
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Memulihkan record yang di-soft-delete.
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

            // Log Aktivitas
            $this->logActivityRestore($id);

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
     * Menduplikasi record berdasarkan primary key.
     */
    public function ajaxClone(mixed $id): ResponseInterface
    {
        $this->ensureInitialized();

        try {
            // Ambil data lama untuk activity log sebelum duplikasi
            $oldData = $this->activityLog !== null ? $this->model->getRawRow($id) : null;

            // Panggil callback sebelum duplikasi
            $this->callbackManager->executeBefore('beforeClone', [
                'table'      => $this->table,
                'primaryKey' => $this->primaryKey,
                'id'         => $id,
            ]);

            // Duplikasi record
            $newId = $this->model->clone($id, $this->cloneExcludeFields);

            if ($newId === false) {
                return $this->jsonResponse(false, [
                    'message' => $this->getLang('clone_fail') ?? 'Failed to clone record.',
                ]);
            }

            // Panggil callback setelah duplikasi
            $this->callbackManager->executeAfter('afterClone', [
                'table'       => $this->table,
                'primaryKey'  => $this->primaryKey,
                'originalId'  => $id,
                'newId'       => $newId,
                'oldData'     => $oldData,
            ]);

            // Catat aktivitas sebagai insert
            $this->logActivityInsert($newId, $oldData ?? []);

            return $this->jsonResponse(true, [
                'message' => $this->getLang('clone_success') ?? 'Record cloned successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mendapatkan daftar record yang dihapus (soft-deleted).
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

        // Tampilkan hanya record yang terhapus
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
     * Mendapatkan data sub-grid untuk sebuah record induk.
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

        // Render HTML sub-grid
        $html = $this->theme->renderSubGrid($config, $records);

        return Services::response()
            ->setContentType('application/json')
            ->setJSON([
                'success' => true,
                'html'    => $html,
            ]);
    }

    /**
     * Tampilan Cetak - mengembalikan halaman HTML bersih yang dapat dicetak.
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
     * Handler AJAX untuk opsi dropdown dependen (cascading).
     *
     * Parameter POST:
     *   - field: Nama field anak
     *   - parent_value: Nilai yang dipilih dari dropdown induk
     */
    public function ajaxDependentOptions(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();

        $field = $request->getPost('field') ?? $request->getGet('field');
        $parentValue  = $request->getPost('parent_value') ?? $request->getGet('parent_value');

        if (!isset($this->dependentRelations[$field])) {
            return $this->jsonResponse(false, ['message' => 'Invalid dependent field.']);
        }

        $config = $this->dependentRelations[$field];

        // Bangun kondisi WHERE untuk FK induk
        $where = [$config['foreignKey'] => $parentValue];

        $options = $this->model->getRelationOptions(
            $config['relatedTable'],
            $config['titleField'],
            $config['keyField'],
            $where,
            $config['where'],
            $config['orderBy']
        );

        return Services::response()
            ->setContentType('application/json')
            ->setJSON([
                'success' => true,
                'options' => $options,
            ]);
    }

    /**
     * Handler AJAX untuk data popover relasi.
     *
     * Parameter POST:
     *   - field: Nama field relasi
     *   - id:    ID record terkait
     */
    public function ajaxRelationPopover(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();

        $field = $request->getPost('field') ?? $request->getGet('field');
        $recordId = $request->getPost('id') ?? $request->getGet('id');

        if (!isset($this->relationPopovers[$field])) {
            return $this->jsonResponse(false, ['message' => 'Popover not configured for this field.']);
        }

        $relInfo = $this->relationManager->getRelationInfo($field);
        if ($relInfo === null) {
            return $this->jsonResponse(false, ['message' => 'Relation not found for this field.']);
        }

        $config = $this->relationPopovers[$field];
        $relatedTable = $relInfo['relatedTable'];
        $keyField = $relInfo['keyField'] ?? 'id';

        $displayFields = $config['displayFields'];
        if (empty($displayFields)) {
            // Deteksi otomatis: default ke key field + title field
            $displayFields = [$keyField, $relInfo['relatedTitleField']];
        }

        // Pastikan key field disertakan
        if (!in_array($keyField, $displayFields, true)) {
            array_unshift($displayFields, $keyField);
        }

        $record = $this->model->getTableRecord($relatedTable, $keyField, $recordId, $displayFields);

        if ($record === null) {
            return $this->jsonResponse(false, ['message' => 'Record not found.']);
        }

        // Bangun HTML untuk popover
        $html = '<div class="gc-popover-body" style="font-size:0.8125rem;min-width:180px;">';
        foreach ($record as $col => $val) {
            $label = $this->columnLabels[$col] ?? ucfirst(str_replace('_', ' ', (string) $col));
            $html .= '<div class="d-flex justify-content-between mb-1 gap-3">';
            $html .= '<strong>' . htmlspecialchars((string) $label) . ':</strong>';
            $html .= '<span class="text-end">' . htmlspecialchars((string) ($val ?? '-')) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $this->jsonResponse(true, ['html' => $html]);
    }

    /**
     * Mengekspor data.
     */
    public function ajaxExport(string $format): ResponseInterface
    {
        $this->ensureInitialized();

        $columns = $this->resolveColumns();

        // Filter kolom jika kolom tertentu diminta untuk ekspor (POST)
        $request = Services::request();
        $exportColumns = $request->getPost('columns');
        if (!empty($exportColumns) && is_array($exportColumns)) {
            $filtered = array_intersect($columns, $exportColumns);
            if (!empty($filtered)) {
                $columns = array_values($filtered);
            }
        }

        // Terapkan filter jika lingkup ekspor adalah "filtered"
        $exportScope = $request->getPost('export_scope') ?? 'all';
        $filters = [];
        if ($exportScope === 'filtered') {
            // Filter kolom sederhana
            $exportFilters = $request->getPost('export_filters');
            if (!empty($exportFilters)) {
                $decoded = json_decode($exportFilters, true);
                if (is_array($decoded)) {
                    $filters = $decoded;
                }
                $this->model->setFilterTypes(array_fill_keys(array_keys($filters), 'text'));
            }
            // Filter lanjutan dari panel filter
            $exportAdvanced = $request->getPost('export_advanced_filters');
            if (!empty($exportAdvanced)) {
                $decoded = json_decode($exportAdvanced, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $af) {
                        if (!empty($af['field']) && !empty($af['value'])) {
                            $this->model->addAdvancedFilter(
                                $af['field'],
                                $af['operator'] ?? 'contains',
                                $af['value']
                            );
                        }
                    }
                }
            }
        }

        $records = $this->model->getList(
            $columns,
            0, // no limit
            0,
            $this->orderBy,
            $this->where,
            null, // no search
            [],   // no searchable columns
            $filters
        );

        // Catatan: callback kolom TIDAK diterapkan pada ekspor (data mentah saja)
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

        // Format tidak dikenal
        return $this->jsonResponse(false, ['message' => 'Unknown export format.']);
    }

    // ======== Import Methods ========

    /**
     * Mendapatkan HTML form impor.
     */
    public function ajaxImportForm(): ResponseInterface
    {
        $this->ensureInitialized();

        $fields = $this->resolveFields('add');
        $importData = [
            'fields'        => $fields,
            'fieldLabels'   => $this->columnLabels,
            'crudId'        => $this->crudId,
            'subject'       => $this->subject,
            'language'      => $this->languageStrings,
            'primaryKey'    => $this->primaryKey,
            'templateUrl'   => $this->getImportTemplateUrl(),
        ];

        return Services::response()
            ->setContentType('application/json')
            ->setJSON([
                'success' => true,
                'html'    => $this->theme->renderImportForm($importData),
            ]);
    }

    /**
     * Membangun URL untuk mengunduh template CSV impor.
     */

    private function getImportTemplateUrl(): string
    {
        $request = Services::request();
        $uri = (string) $request->getUri();

        // Parsing parameter query yang ada
        $parsed = parse_url($uri);
        $query = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }
        $query['gc_action'] = 'import_template';

        $base = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }
        $base .= $parsed['path'] ?? '/';

        return $base . '?' . http_build_query($query);
    }

    /**
     * Mengunggah dan mengurai file impor, mengembalikan pratinjau + pemetaan.
     */
    public function ajaxImportUpload(): ResponseInterface
    {
        $this->ensureInitialized();

        $request = Services::request();
        $file = $request->getFile('import_file');

        if ($file === null || !$file->isValid() || $file->hasMoved()) {
            return $this->jsonResponse(false, [
                'message' => $this->getLang('import_file_required') ?? 'Please select a file to import.',
            ]);
        }

        try {
            $importManager = $this->getImportManager();
            $result = $importManager->parse([
                'name'     => $file->getName(),
                'tmp_name' => $file->getTempName(),
                'size'     => $file->getSize(),
                'error'    => $file->getError(),
            ]);

            // Deteksi otomatis pemetaan kolom
            $fields = $this->resolveFields('add');
            $mapping = $importManager->detectMapping(
                $result['headers'],
                $fields,
                $this->columnLabels
            );

            return $this->jsonResponse(true, [
                'headers'   => $result['headers'],
                'preview'   => $result['preview'],
                'totalRows' => $result['totalRows'],
                'filename'  => $file->getName(),
                'mapping'   => $mapping,
                'fields'    => $fields,
                'fieldLabels' => $this->columnLabels,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(false, [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Menjalankan impor dengan kolom yang sudah dipetakan.
     */
    public function ajaxImportExecute(): ResponseInterface
    {
        $this->ensureInitialized();

        $request = Services::request();
        $rowsJson = $request->getPost('rows');
        $mappingJson = $request->getPost('mapping');

        if (empty($rowsJson) || empty($mappingJson)) {
            return $this->jsonResponse(false, [
                'message' => $this->getLang('import_no_data') ?? 'No data to import.',
            ]);
        }

        $rows = json_decode($rowsJson, true);
        $mapping = json_decode($mappingJson, true);

        if (!is_array($rows) || !is_array($mapping)) {
            return $this->jsonResponse(false, [
                'message' => 'Invalid import data.',
            ]);
        }

        // Petakan baris: header-indexed → field-indexed
        $mappedRows = [];
        foreach ($rows as $row) {
            $mapped = [];
            foreach ($mapping as $headerIndex => $fieldName) {
                if ($fieldName !== null && $fieldName !== '' && isset($row[$headerIndex])) {
                    $mapped[$fieldName] = $row[$headerIndex];
                }
            }
            if (!empty($mapped)) {
                $mappedRows[] = $mapped;
            }
        }

        if (empty($mappedRows)) {
            return $this->jsonResponse(false, [
                'message' => $this->getLang('import_no_data') ?? 'No data to import.',
            ]);
        }

        $importManager = $this->getImportManager();
        $result = $importManager->execute($mappedRows, function (array $row) {
            try {
                // Periksa callback beforeInsert
                $row = $this->callbackManager->executeBefore('beforeInsert', $row);

                // Hapus field N-to-N (virtual, bukan kolom nyata)
                $row = $this->stripNtoNFields($row);

                $insertId = $this->model->insert($row);

                if ($insertId) {
                    $this->callbackManager->executeAfter('afterInsert', [
                        'table'      => $this->table,
                        'primaryKey' => $this->primaryKey,
                        'insertId'   => $insertId,
                        'data'       => $row,
                    ]);
                }

                return $insertId;
            } catch (\Throwable $e) {
                return false;
            }
        });

        $total = count($mappedRows);
        $message = $result['imported'] > 0
            ? str_replace(['{imported}', '{total}'], [(string) $result['imported'], (string) $total], $this->getLang('import_success') ?? 'Successfully imported {imported} of {total} records.')
            : ($this->getLang('import_error') ?? 'Import failed.');

        return $this->jsonResponse(true, [
            'imported' => $result['imported'],
            'total'    => $total,
            'errors'   => $result['errors'],
            'message'  => $message,
        ]);
    }

    /**
     * Membuat dan mengunduh template CSV berdasarkan field CRUD yang aktif.
     */

    public function ajaxImportTemplate(): ResponseInterface
    {
        $this->ensureInitialized();

        $request = Services::request();
        $selectedFields = $request->getGet('fields');

        $fields = $this->resolveFields('add');
        $labels = $this->columnLabels;

        // Jika field tertentu diminta, filter hanya itu
        if (!empty($selectedFields) && is_array($selectedFields)) {
            $fields = array_intersect($fields, $selectedFields);
        }

        // Bangun CSV di memori
        $output = fopen('php://temp', 'r+');

        // Header: gunakan label field jika tersedia, jika tidak gunakan nama field
        $header = [];
        foreach ($fields as $field) {
            $header[] = $labels[$field] ?? $field;
        }
        fputcsv($output, $header);

        // Baris contoh dengan placeholder
        $sample = [];
        foreach ($fields as $field) {
            $sample[] = $this->getSampleValue($field);
        }
        fputcsv($output, $sample);

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $filename = $this->table . '_import_template.csv';

        return Services::response()
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    /**
     * Menghasilkan nilai contoh untuk sebuah field berdasarkan nama/tipe.
     */

    private function getSampleValue(string $field): string
    {
        $lower = strtolower($field);

        if (str_contains($lower, 'name') || str_contains($lower, 'nama')) {
            return 'John Doe';
        }
        if (str_contains($lower, 'email')) {
            return 'user@example.com';
        }
        if (str_contains($lower, 'phone') || str_contains($lower, 'telp') || str_contains($lower, 'hp') || str_contains($lower, 'telepon')) {
            return '08123456789';
        }
        if (str_contains($lower, 'address') || str_contains($lower, 'alamat')) {
            return 'Jl. Merdeka No. 1';
        }
        if (str_contains($lower, 'price') || str_contains($lower, 'harga')) {
            return '50000';
        }
        if (str_contains($lower, 'active') || str_contains($lower, 'aktif') || $field === 'is_active') {
            return '1';
        }
        if (str_contains($lower, 'desc') || str_contains($lower, 'keterangan')) {
            return 'Sample description text';
        }
        if (str_contains($lower, 'id') && !str_contains($lower, 'uuid')) {
            return '';
        }
        if (str_contains($lower, 'date') || str_contains($lower, 'tanggal') || str_contains($lower, 'tgl')) {
            return date('Y-m-d');
        }
        if (str_contains($lower, 'qty') || str_contains($lower, 'stock') || str_contains($lower, 'stok') || str_contains($lower, 'jumlah') || str_contains($lower, 'count')) {
            return '10';
        }

        return 'Sample ' . $field;
    }

    /**
     * Mendapatkan (atau membuat) ImportManager.
     */
    private function getImportManager(): ImportManager
    {
        if ($this->importManager === null) {
            $this->importManager = new ImportManager($this->config);
        }
        return $this->importManager;
    }

    // ======== Internal Methods ========

    /**
     * Memastikan tabel sudah diatur sebelum operasi.
     */
    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            throw GroceryCrudException::tableNotSet();
        }
    }

    /**
     * Membangun array data untuk rendering daftar.
     */
    private function buildListData(int $page = 1, ?string $search = null, ?int $perPage = null, ?string $sortField = null, ?string $sortDir = null, array $filters = [], array $advancedFilters = [], array $columns = [], bool $trashedView = false): array
    {
        // Filter aksi dan fitur berdasarkan izin
        $allowedActions = ['add', 'edit', 'delete'];
        foreach ($allowedActions as $action) {
            if (!$this->hasPermission($action)) {
                $this->actions = array_filter($this->actions, fn($a) => $a !== $action);
            }
        }

        // Filter aksi batch berdasarkan izin
        if (!$this->hasPermission('delete')) {
            $this->batchActions = array_filter($this->batchActions, fn($label, $id) => !in_array($id, ['delete_selected', 'restore_selected'], true), ARRAY_FILTER_USE_BOTH);
        }

        // Nonaktifkan ekspor jika tidak diizinkan
        if (!$this->hasPermission('export')) {
            $this->enableExport = false;
        }

        // Nonaktifkan impor jika tidak diizinkan
        if (!$this->hasPermission('import')) {
            $this->enableImport = false;
        }

        $perPage = $perPage ?? $this->perPage;
        $offset = ($page - 1) * $perPage;
        if (empty($columns)) {
            $columns = $this->resolveColumns();
        }

        $searchableColumns = $this->searchable ? $columns : [];

        // Gabungkan sort request dengan orderBy default (sort request diutamakan)
        $orders = $this->orderBy;
        if ($sortField !== null && $sortField !== '') {
            $direction = strtoupper($sortDir ?? 'ASC');
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                $direction = 'ASC';
            }
            array_unshift($orders, ['field' => $sortField, 'direction' => $direction]);
        }

        // Atur tipe filter agar model tahu LIKE vs pencocokan tepat
        $filterTypes = [];
        foreach ($this->columnFilters as $field => $config) {
            $filterTypes[$field] = $config['type'] ?? 'dropdown';
        }
        $this->model->setFilterTypes($filterTypes);

        // Terapkan filter lanjutan
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

        // Terapkan callback kolom
        $columnCallbacks = $this->callbackManager->getColumnCallbacks();
        foreach ($records as &$row) {
            // Pastikan _raw ada (diisi oleh getList model)
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

        // Ambil opsi relasi untuk filter kolom
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

        // Bangun opsi field untuk kolom (label dropdown, dll.)
        $fieldOptions = [];
        foreach ($columns as $col) {
            if (isset($this->fieldTypeOverrides[$col]) && !empty($this->fieldTypeOverrides[$col]['options'])) {
                $fieldOptions[$col] = $this->fieldTypeOverrides[$col]['options'];
            }
        }

        // Bangun info field pengeditan inline
        $inlineEditFieldTypes = [];
        $inlineFieldInfo = [];
        if ($this->enableInlineEditing) {
            // Tentukan kolom mana yang dapat diedit inline
            $editableColumns = !empty($this->inlineEditColumns)
                ? array_intersect($columns, $this->inlineEditColumns)
                : $columns;

            foreach ($editableColumns as $col) {
                $info = $this->getInlineFieldInfo($col);
                $inlineEditFieldTypes[$col] = $info['type'];
                if (!empty($info['options'])) {
                    $inlineFieldInfo[$col] = $info['options'];
                    // Juga pastikan fieldOptions memiliki ini untuk pemetaan tampilan
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
            'enableImport'         => $this->enableImport,
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
            'relationPopovers'     => $this->relationPopovers,
            'enableActivityLogViewer' => $this->enableActivityLogViewer && $this->activityLog !== null,
            'calendarField'       => $this->calendarField,
            'calendarTitleField'  => $this->calendarTitleField,
            'enableFileManager'   => $this->fileManagerExplicitlyEnabled,
            'enableClone'          => $this->enableClone,
            'cloneExcludeFields'   => $this->cloneExcludeFields,
            'dbSettings'           => $this->loadDbSettings(),
            'hasDbSettings'        => $this->settingUserIdResolver !== null,
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
            // Gunakan baris mentah untuk mempertahankan nilai FK (misal category_id=1, bukan "Electronics")
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

        // Selesaikan nilai repeater
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

        // Deteksi tipe dan nilai field
        foreach ($fields as $field) {
            // Lewati field repeater (sudah diselesaikan di atas)
            if (isset($this->repeaterFields[$field])) {
                continue;
            }

            // Deteksi tipe
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

            // Nilai
            if ($mode === 'edit' && $record !== null) {
                $fieldValues[$field] = $record[$field] ?? '';
            } elseif ($mode === 'add') {
                $fieldValues[$field] = '';
            }

            // Opsi untuk dropdown/relasi (lewati jika sudah diatur melalui setFieldType)
            if (isset($fieldOptions[$field])) {
                // Opsi kustom disediakan, lewati deteksi otomatis
            } elseif (isset($this->dependentRelations[$field])) {
                // Dropdown dependen: lewati memuat SEMUA opsi saat muat halaman.
                // Jika mengedit, tetap tambahkan nilai yang dipilih saat ini sebagai opsi
                // agar dropdown menampilkannya di awal sebelum AJAX memuat sisanya.
                $depCfg = $this->dependentRelations[$field];
                if ($mode === 'edit' && !empty($fieldValues[$field])) {
                    $currentOptions = $this->model->getRelationOptions(
                        $depCfg['relatedTable'],
                        $depCfg['titleField'],
                        $depCfg['keyField'],
                        [$depCfg['keyField'] => $fieldValues[$field]],
                        $depCfg['where'],
                        $depCfg['orderBy']
                    );
                    $fieldOptions[$field] = [];
                    foreach ($currentOptions as $item) {
                        $fieldOptions[$field][$item['id']] = $item['title'];
                    }
                } else {
                    $fieldOptions[$field] = [];
                }
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
                // Data NtoN untuk checklist
                $relData = $this->relationManager->getRelationNtoNData($field);
                $options = [];
                foreach ($relData as $item) {
                    $options[$item['id']] = $item['title'];
                }
                $fieldOptions[$field] = $options;

                // Nilai yang dipilih
                if ($mode === 'edit' && $id !== null) {
                    $fieldValues[$field] = $this->relationManager->getRelationNtoNValues($field, $id);
                }
            }

            // Field unggahan
            if (isset($this->uploadFieldConfigs[$field])) {
                $uploadFields[$field] = true;
                $fieldTypes[$field] = 'file';
                if (!empty($fieldValues[$field])) {
                    $fieldValues[$field] = $this->uploadManager->getUploadUrl($field, $fieldValues[$field]);
                }
            }
        }

        // Terapkan callback field
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
            'dependsOn'          => $this->dependsOn,
            'dependentRelations' => $this->dependentRelations,
            'fieldGroups'        => $this->fieldGroups,
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

        // Default: semua kolom kecuali primary key
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

        // Default: kecualikan primary key dari form
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
                        // Hapus file lama jika memperbarui
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
                // Pertahankan file yang ada jika tidak ada unggahan baru
                $existing = $request->getPost($field . '_existing');
                if ($existing) {
                    // Keamanan: jika data lama menyimpan URL lengkap, ekstrak hanya nama file
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
            // Hapus relasi yang ada
            $this->db->table($rel['junctionTable'])
                ->where($rel['primaryKeyInJunction'], $id)
                ->delete();

            // Masukkan relasi baru
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
     * Memeriksa apakah nilai relasi harus dianggap kosong/tidak valid.
     *
     * @param  mixed $value
     * @return bool
     */
    private function isEmptyRelationValue(mixed $value): bool
    {
        return $value === '' || $value === null || $value === '0' || $value === 0;
    }

    /**
     * Menangani data relasi N-to-N saat penghapusan.
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
     * Menghapus field relasi N-to-N dari array data.
     *
     * Field N-to-N (misal tags) bersifat virtual — tidak ada sebagai kolom
     * di tabel utama. Mereka harus dihapus sebelum insert/update untuk
     * menghindari error SQL "unknown column".
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
     * Menyaring aksi batch berdasarkan mode tampilan.
     *
     * Tampilan aktif: hanya tampilkan delete_selected (sembunyikan restore_selected)
     * Tampilan trash: tampilkan delete_selected dan restore_selected
     *
     * @param bool $trashedView
     * @return array<string, string>
     */
    private function filterBatchActions(bool $trashedView): array
    {
        if ($trashedView) {
            // Tampilan trash: tampilkan semua aksi batch
            return $this->batchActions;
        }

        // Tampilan aktif: tampilkan hanya delete_selected
        return array_intersect_key($this->batchActions, ['delete_selected' => true]);
    }

    /**
     * Memetakan aksi AJAX ke nama izin.
     */
    private function getActionPermission(string $action): string
    {
        return match ($action) {
            'add_form', 'add', 'clone', 'custom_action'  => 'add',
            'edit_form', 'edit', 'inline_save'       => 'edit',
            'delete', 'batch_action', 'restore'      => 'delete',
            'export', 'print_view'                => 'export',
            'import_form', 'import_upload', 'import_execute', 'import_template' => 'import',
            'list', 'trash_list', 'sub_grid', 'load_settings', 'save_settings' => 'view',
            'file_manager', 'file_manager_list', 'file_manager_upload',
            'file_manager_create_folder', 'file_manager_rename',
            'file_manager_delete', 'file_manager_tree',
            'file_manager_search', 'file_manager_move',
            'file_manager_copy', 'file_manager_file_info' => 'view',
            default                                   => 'view',
        };
    }

    /**
     * Menangani perutean aksi AJAX.
     */
    private function handleAjaxAction(string $action): ResponseInterface
    {
        // Periksa izin untuk aksi ini
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
            'import_form'   => $this->ajaxImportForm(),
            'import_upload' => $this->ajaxImportUpload(),
            'import_execute'=> $this->ajaxImportExecute(),
            'import_template' => $this->ajaxImportTemplate(),
            'file_manager'          => $this->ajaxFileManager(),
            'file_manager_list'     => $this->ajaxFileManagerList(),
            'file_manager_upload'   => $this->ajaxFileManagerUpload(),
            'file_manager_create_folder' => $this->ajaxFileManagerCreateFolder(),
            'file_manager_rename'   => $this->ajaxFileManagerRename(),
            'file_manager_delete'   => $this->ajaxFileManagerDelete(),
            'file_manager_tree'     => $this->ajaxFileManagerTree(),
            'file_manager_search'   => $this->ajaxFileManagerSearch(),
            'file_manager_move'     => $this->ajaxFileManagerMove(),
            'file_manager_copy'     => $this->ajaxFileManagerCopy(),
            'file_manager_file_info' => $this->ajaxFileManagerFileInfo(),
            'batch_action'  => $this->ajaxBatchAction(),
            'custom_action' => $this->ajaxCustomAction($this->getRequestId()),
            'save_settings' => $this->ajaxSaveSettings(),
            'load_settings' => $this->ajaxLoadSettings(),
            'clone'         => $this->ajaxClone($this->getRequestId()),
            'restore'       => $this->ajaxRestore($this->getRequestId()),
            'trash_list'    => $this->ajaxTrashList(),
            'sub_grid'      => $this->ajaxSubGrid(),
            'inline_save'         => $this->ajaxInlineSave(),
            'dependent_options'   => $this->ajaxDependentOptions(),
            'relation_popover'    => $this->ajaxRelationPopover(),
            'activity_log_viewer' => $this->ajaxActivityLogViewer(),
            'activity_log_data'   => $this->ajaxActivityLogData(),
            'activity_log_detail' => $this->ajaxActivityLogDetail(),
            'calendar_data'       => $this->ajaxCalendarData(),
            'release_lock'        => $this->ajaxReleaseLock(),
            default               => $this->jsonResponse(false, ['message' => 'Invalid action.']),
        };
    }

    /**
     * Menangani perutean aksi API (mode REST API).
     *
     * Memetakan aksi ke handler API khusus yang mengembalikan JSON terstandarisasi
     * dengan kode status HTTP yang tepat, tanpa HTML.
     */
    private function handleApiAction(string $action): ResponseInterface
    {
        // Periksa izin untuk aksi ini
        $requiredPermission = $this->getActionPermission($action);
        if (!$this->hasPermission($requiredPermission)) {
            return $this->apiError(
                $this->getLang('permission_denied') ?? 'Permission denied.',
                403
            );
        }

        return match ($action) {
            'list'          => $this->apiList(),
            'read'          => $this->apiRead($this->getRequestId()),
            'add'           => $this->apiCreate(),
            'add_form'      => $this->apiFormData('add'),
            'edit'          => $this->apiUpdate($this->getRequestId()),
            'edit_form'     => $this->apiFormData('edit', $this->getRequestId()),
            'delete'        => $this->apiDelete($this->getRequestId()),
            'clone'         => $this->apiClone($this->getRequestId()),
            'restore'       => $this->apiRestore($this->getRequestId()),
            'trash_list'    => $this->apiTrashList(),
            'export'        => $this->ajaxExport($this->getExportFormat()),
            'import_upload' => $this->apiImportUpload(),
            'import_execute'=> $this->apiImportExecute(),
            'import_template' => $this->ajaxImportTemplate(),
            'batch_action'  => $this->apiBatchAction(),
            'inline_save'   => $this->ajaxInlineSave(),
            default         => $this->apiError('Invalid API action.', 404),
        };
    }

    // ======== REST API Handlers ========

    /**
     * API: Menampilkan daftar record dengan paginasi.
     *
     * GET /api/table?page=1&perPage=10&search=...
     */
    private function apiList(): ResponseInterface
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

        $listData = $this->buildListData(
            max(1, $page), $search, $perPage, $sortField, $sortDir,
            $filters, $advancedFilters, $this->resolveColumns()
        );

        return $this->apiResponse(
            $listData['records'],
            '',
            200,
            [
                'total'      => (int) ($listData['totalCount'] ?? 0),
                'page'       => max(1, $page),
                'perPage'    => $perPage,
                'totalPages' => (int) ceil(($listData['totalCount'] ?? 0) / max(1, $perPage)),
            ]
        );
    }

    /**
     * API: Membaca satu record.
     *
     * GET /api/table?id=123
     */
    private function apiRead(mixed $id): ResponseInterface
    {
        if (empty($id)) {
            return $this->apiError('Record ID is required.', 400);
        }

        $this->ensureInitialized();

        $columns = $this->resolveColumns();
        $record = $this->model->getRow($id);

        if ($record === null || $record === false) {
            return $this->apiError('Record not found.', 404);
        }

        // Terapkan callback kolom
        $columnCallbacks = $this->callbackManager->getColumnCallbacks();
        foreach ($columnCallbacks as $field => $callback) {
            if (isset($record[$field])) {
                $record[$field] = $callback($record[$field], $record);
            }
        }

        // Filter ke kolom yang diminta jika ditentukan
        if (!empty($columns)) {
            $record = array_intersect_key($record, array_flip($columns));
        }

        return $this->apiResponse($record);
    }

    /**
     * API: Membuat record baru.
     *
     * POST /api/table dengan body JSON atau data form
     */
    private function apiCreate(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();
        $data = $request->getPost() ?? $request->getJSON(true) ?? [];

        // Hapus kunci aksi
        unset($data['gc_action']);

        // Lewati validasi untuk field yang dinonaktifkan melalui dependsOn (action='enable')
        $this->filterDependsOnValidationRules($data);

        // Validasi
        $errors = $this->validationManager->validate($data);
        if (!empty($errors)) {
            return $this->apiError(
                $this->getLang('insert_fail') ?? 'Validation failed.',
                422,
                $errors
            );
        }

        try {
            // Callback sebelum insert
            $data = $this->callbackManager->executeBefore('beforeInsert', $data);

            // Tangani unggahan
            $data = $this->handleUploads($data);
            if ($data === false) {
                return $this->apiError($this->getLang('upload_error') ?? 'Upload failed.', 400);
            }

            // Proses field repeater
            $this->processRepeaterDataBeforeSave($data);

            // Hapus field N-to-N
            $data = $this->stripNtoNFields($data);

            // Lakukan insert
            $insertId = $this->model->insert($data);

            if ($insertId === false || $insertId === 0) {
                return $this->apiError(
                    $this->getLang('insert_fail') ?? 'Failed to insert record.',
                    500
                );
            }

            // Tangani data hasMany repeater
            $this->processRepeaterDataAfterSave($insertId);

            // Tangani relasi NtoN
            $this->handleNtoNInsert($insertId, $data);

            // Callback setelah insert
            $this->callbackManager->executeAfter('afterInsert', [
                'table'         => $this->table,
                'primaryKey'    => $this->primaryKey,
                'insertId'      => $insertId,
                'data'          => $data,
            ]);

            return $this->apiResponse(
                [$this->primaryKey => $insertId],
                $this->getLang('insert_success') ?? 'Record inserted successfully.',
                201
            );
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * API: Update a record.
     *
     * PUT /api/table?id=123 with JSON body or form data
     */
    private function apiUpdate(mixed $id): ResponseInterface
    {
        if (empty($id)) {
            return $this->apiError('Record ID is required.', 400);
        }

        $this->ensureInitialized();
        $request = Services::request();
        $data = $request->getPost() ?? $request->getJSON(true) ?? [];

        unset($data['gc_action'], $data[$this->primaryKey]);

        // Validasi (unique abaikan record saat ini)
        foreach ($this->uniqueFields as $uniqueField) {
            if (isset($data[$uniqueField])) {
                $this->validationManager->uniqueExcept($uniqueField, $id, $this->columnLabels[$uniqueField] ?? null);
            }
        }

        $errors = $this->validationManager->validate($data);
        if (!empty($errors)) {
            return $this->apiError(
                $this->getLang('update_fail') ?? 'Validation failed.',
                422,
                $errors
            );
        }

        try {
            // Callback sebelum update
            $data = $this->callbackManager->executeBefore('beforeUpdate', $data);

            // Tangani unggahan
            $data = $this->handleUploads($data, $id);
            if ($data === false) {
                return $this->apiError($this->getLang('upload_error') ?? 'Upload failed.', 400);
            }

            // Pertahankan file yang ada
            foreach ($this->uploadFieldConfigs as $field => $config) {
                if (!isset($data[$field]) && $request->getPost($field . '_existing')) {
                    $data[$field] = $request->getPost($field . '_existing');
                }
            }

            // Hapus kunci _existing
            foreach (array_keys($data) as $key) {
                if (str_ends_with($key, '_existing')) {
                    unset($data[$key]);
                }
            }

            // Proses field repeater
            $this->processRepeaterDataBeforeSave($data);

            // Hapus field N-to-N
            $data = $this->stripNtoNFields($data);

            // Lakukan update
            $updated = $this->model->update($id, $data);

            // Tangani data hasMany repeater
            $this->processRepeaterDataAfterSave($id);

            if (!$updated) {
                return $this->apiError(
                    $this->getLang('update_fail') ?? 'Failed to update record.',
                    500
                );
            }

            // Tangani relasi NtoN
            $this->handleNtoNUpdate($id, $data);

            // Callback setelah update
            $this->callbackManager->executeAfter('afterUpdate', [
                'table'         => $this->table,
                'primaryKey'    => $this->primaryKey,
                'id'            => $id,
                'data'          => $data,
            ]);

            return $this->apiResponse(
                [$this->primaryKey => $id],
                $this->getLang('update_success') ?? 'Record updated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * API: Delete a record.
     *
     * DELETE /api/table?id=123
     */
    private function apiDelete(mixed $id): ResponseInterface
    {
        if (empty($id)) {
            return $this->apiError('Record ID is required.', 400);
        }

        $this->ensureInitialized();

        try {
            // Callback sebelum hapus
            $this->callbackManager->executeBefore('beforeDelete', [
                'table'         => $this->table,
                'primaryKey'    => $this->primaryKey,
                'id'            => $id,
            ]);

            // Hapus record NtoN terkait
            $this->handleNtoNDelete($id);

            $deleted = $this->model->delete($id);

            if (!$deleted) {
                return $this->apiError(
                    $this->getLang('delete_fail') ?? 'Failed to delete record.',
                    500
                );
            }

            // Callback setelah hapus
            $this->callbackManager->executeAfter('afterDelete', [
                'table'         => $this->table,
                'primaryKey'    => $this->primaryKey,
                'id'            => $id,
            ]);

            return $this->apiResponse(
                null,
                $this->getLang('delete_success') ?? 'Record deleted successfully.'
            );
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * API: Restore a soft-deleted record.
     */
    private function apiRestore(mixed $id): ResponseInterface
    {
        if (empty($id)) {
            return $this->apiError('Record ID is required.', 400);
        }

        $this->ensureInitialized();

        try {
            $restored = $this->model->restore($id);

            if (!$restored) {
                return $this->apiError(
                    $this->getLang('restore_fail') ?? 'Failed to restore record.',
                    500
                );
            }

            return $this->apiResponse(
                null,
                $this->getLang('restore_success') ?? 'Record restored successfully.'
            );
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * API: Menduplikasi record berdasarkan primary key.
     */
    private function apiClone(mixed $id): ResponseInterface
    {
        if (empty($id)) {
            return $this->apiError('Record ID is required.', 400);
        }

        $this->ensureInitialized();

        try {
            $oldData = $this->activityLog !== null ? $this->model->getRawRow($id) : null;

            // Panggil callback sebelum duplikasi
            $this->callbackManager->executeBefore('beforeClone', [
                'table'      => $this->table,
                'primaryKey' => $this->primaryKey,
                'id'         => $id,
            ]);

            // Duplikasi record
            $newId = $this->model->clone($id, $this->cloneExcludeFields);

            if ($newId === false) {
                return $this->apiError(
                    $this->getLang('clone_fail') ?? 'Failed to clone record.',
                    500
                );
            }

            // Panggil callback setelah duplikasi
            $this->callbackManager->executeAfter('afterClone', [
                'table'       => $this->table,
                'primaryKey'  => $this->primaryKey,
                'originalId'  => $id,
                'newId'       => $newId,
                'oldData'     => $oldData,
            ]);

            // Catat aktivitas sebagai insert
            $this->logActivityInsert($newId, $oldData ?? []);

            return $this->apiResponse(
                [$this->primaryKey => $newId],
                $this->getLang('clone_success') ?? 'Record cloned successfully.',
                201
            );
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * API: List trashed (soft-deleted) records.
     */
    private function apiTrashList(): ResponseInterface
    {
        $this->ensureInitialized();

        $request = Services::request();
        $page = (int) ($request->getGet('page') ?? $request->getPost('page') ?? 1);
        $search = $request->getGet('search') ?? $request->getPost('search') ?? null;
        $perPage = (int) ($request->getGet('perPage') ?? $request->getPost('perPage') ?? $this->perPage);
        $sortField = $request->getGet('sort_field') ?? $request->getPost('sort_field') ?? null;
        $sortDir = $request->getGet('sort_dir') ?? $request->getPost('sort_dir') ?? null;

        $this->model->onlyTrashed();

        $listData = $this->buildListData(
            max(1, $page), $search, $perPage, $sortField, $sortDir,
            [], [], [], true
        );

        return $this->apiResponse(
            $listData['records'],
            '',
            200,
            [
                'total'      => (int) ($listData['totalCount'] ?? 0),
                'page'       => max(1, $page),
                'perPage'    => $perPage,
                'totalPages' => (int) ceil(($listData['totalCount'] ?? 0) / max(1, $perPage)),
            ]
        );
    }

    /**
     * API: Return form field definitions (no HTML).
     *
     * Useful for SPA frontends to dynamically build forms.
     *
     * GET /api/table?gc_action=add_form
     * GET /api/table?id=123&gc_action=edit_form
     */
    private function apiFormData(string $mode, mixed $id = null): ResponseInterface
    {
        $this->ensureInitialized();

        if ($mode === 'edit' && empty($id)) {
            return $this->apiError('Record ID is required.', 400);
        }

        $data = $this->buildFormData($mode, $id);

        if ($data === null) {
            return $this->apiError('Record not found.', 404);
        }

        // Format definisi field untuk konsumsi SPA
        $fields = [];
        foreach ($data['fields'] as $field) {
            $fields[] = [
                'name'      => $field,
                'label'     => $data['fieldLabels'][$field] ?? $field,
                'type'      => $data['fieldTypes'][$field] ?? 'text',
                'value'     => $data['fieldValues'][$field] ?? '',
                'options'   => $data['fieldOptions'][$field] ?? null,
                'required'  => in_array($field, $this->requiredFields, true),
                'readOnly'  => in_array($field, $this->readOnlyFields, true),
                'unique'    => in_array($field, $this->uniqueFields, true),
            ];
        }

        return $this->apiResponse([
            'fields'    => $fields,
            'primaryKey'=> $data['primaryKey'],
            'subject'   => $this->subject,
        ]);
    }

    /**
     * API: Upload and parse import file.
     */
    private function apiImportUpload(): ResponseInterface
    {
        $this->ensureInitialized();

        $request = Services::request();
        $file = $request->getFile('import_file');

        if ($file === null || !$file->isValid() || $file->hasMoved()) {
            return $this->apiError(
                $this->getLang('import_file_required') ?? 'Please select a file to import.',
                400
            );
        }

        try {
            $importManager = $this->getImportManager();
            $result = $importManager->parse([
                'name'     => $file->getName(),
                'tmp_name' => $file->getTempName(),
                'size'     => $file->getSize(),
                'error'    => $file->getError(),
            ]);

            $fields = $this->resolveFields('add');
            $mapping = $importManager->detectMapping(
                $result['headers'],
                $fields,
                $this->columnLabels
            );

            return $this->apiResponse([
                'headers'     => $result['headers'],
                'preview'     => $result['preview'],
                'totalRows'   => $result['totalRows'],
                'filename'    => $file->getName(),
                'mapping'     => $mapping,
                'fields'      => $fields,
                'fieldLabels' => $this->columnLabels,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), 400);
        }
    }

    /**
     * API: Execute import with mapped columns.
     */
    private function apiImportExecute(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();

        $headers = $request->getPost('headers') ?? [];
        $rows = $request->getPost('rows') ?? [];
        $mapping = $request->getPost('mapping') ?? [];
        $fields = $this->resolveFields('add');

        // Petakan baris menggunakan pemetaan kolom
        $mappedRows = [];
        foreach ($rows as $row) {
            $mapped = [];
            foreach ($mapping as $csvIndex => $field) {
                if (in_array($field, $fields, true)) {
                    $mapped[$field] = $row[$csvIndex] ?? '';
                }
            }
            if (!empty($mapped)) {
                $mappedRows[] = $mapped;
            }
        }

        if (empty($mappedRows)) {
            return $this->apiError(
                $this->getLang('import_no_data') ?? 'No data to import.',
                400
            );
        }

        $importManager = $this->getImportManager();
        $result = $importManager->execute($mappedRows, function (array $row) {
            try {
                $row = $this->callbackManager->executeBefore('beforeInsert', $row);
                $row = $this->stripNtoNFields($row);
                $insertId = $this->model->insert($row);

                if ($insertId) {
                    $this->callbackManager->executeAfter('afterInsert', [
                        'table'      => $this->table,
                        'primaryKey' => $this->primaryKey,
                        'insertId'   => $insertId,
                        'data'       => $row,
                    ]);
                }

                return $insertId;
            } catch (\Throwable $e) {
                return false;
            }
        });

        $total = count($mappedRows);

        return $this->apiResponse(
            [
                'imported' => $result['imported'],
                'total'    => $total,
                'errors'   => $result['errors'],
            ],
            $result['imported'] > 0
                ? str_replace(
                    ['{imported}', '{total}'],
                    [(string) $result['imported'], (string) $total],
                    $this->getLang('import_success') ?? 'Successfully imported {imported} of {total} records.'
                )
                : ($this->getLang('import_error') ?? 'Import failed.')
        );
    }

    /**
     * API: Execute batch action.
     */
    private function apiBatchAction(): ResponseInterface
    {
        $this->ensureInitialized();
        $request = Services::request();
        $batchAction = $request->getPost('batch_action') ?? $request->getGet('batch_action');
        $ids = $request->getPost('ids') ?? $request->getGet('ids') ?? [];

        if (empty($batchAction) || !isset($this->batchActions[$batchAction])) {
            return $this->apiError('Invalid batch action.', 400);
        }

        if (!is_array($ids) || empty($ids)) {
            return $this->apiError('No records selected.', 400);
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
                return $this->apiResponse(
                    null,
                    $this->getLang('batch_success') ?? 'Batch action completed.'
                );
            }

            return $this->apiError(
                $this->getLang('batch_fail') ?? 'Batch action failed.',
                500
            );
        } catch (\Throwable $e) {
            return $this->apiError($e->getMessage(), 500);
        }
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
                // Log Aktivitas for batch actions
                if ($this->activityLog !== null) {
                    $actionType = match ($batchAction) {
                        'delete_selected' => 'batch_delete',
                        'restore_selected' => 'batch_restore',
                        default => null,
                    };
                    if ($actionType !== null) {
                        $this->logActivityBatch($actionType, $ids);
                    }
                }

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
     * Mendapatkan ID request dari POST atau GET.
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
     * Mendapatkan format ekspor dari request.
     */
    private function getExportFormat(): string
    {
        $request = Services::request();
        return $request->getPost('format') ?? $request->getGet('format') ?? 'csv';
    }

    /**
     * Membangun daftar format ekspor yang tersedia.
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
     * Mendapatkan string bahasa.
     */
    private function getLang(string $key): string
    {
        return $this->languageStrings[$key] ?? $key;
    }

    // ======== Activity Log Internal Helpers ========

    /**
     * Mencatat aktivitas insert.
     */
    private function logActivityInsert(mixed $insertId, array $data): void
    {
        if ($this->activityLog === null) {
            return;
        }

        $this->activityLog->logInsert(
            $this->table,
            $insertId,
            $data
        );
    }

    /**
     * Mencatat aktivitas update dengan data lama dan baru.
     */
    private function logActivityUpdate(mixed $id, array $oldData, array $newData): void
    {
        if ($this->activityLog === null) {
            return;
        }

        $this->activityLog->logUpdate(
            $this->table,
            $id,
            $oldData,
            $newData
        );
    }

    /**
     * Mencatat aktivitas delete.
     */
    private function logActivityDelete(mixed $id, ?array $oldData): void
    {
        if ($this->activityLog === null) {
            return;
        }

        $this->activityLog->logDelete(
            $this->table,
            $id,
            $oldData
        );
    }

    /**
     * Mencatat aktivitas restore.
     */
    private function logActivityRestore(mixed $id): void
    {
        if ($this->activityLog === null) {
            return;
        }

        $this->activityLog->logRestore(
            $this->table,
            $id
        );
    }

    /**
     * Mencatat aktivitas batch (delete/restore banyak).
     *
     * @param string $actionType 'batch_delete' atau 'batch_restore'
     * @param array<int, mixed> $ids
     */
    private function logActivityBatch(string $actionType, array $ids): void
    {
        if ($this->activityLog === null) {
            return;
        }

        // Catat setiap ID secara individual untuk ketertelusuran yang lebih baik
        foreach ($ids as $id) {
            if ($actionType === 'batch_delete') {
                $this->activityLog->logDelete($this->table, $id);
            } elseif ($actionType === 'batch_restore') {
                $this->activityLog->logRestore($this->table, $id);
            }
        }
    }

    // ======== Activity Log Viewer Handlers ========

    /**
     * Merender halaman penampil Activity Log.
     *
     * Mengganti tampilan daftar CRUD utama dengan penampil activity log
     * yang dapat dijelajahi dan difilter, menampilkan semua operasi yang tercatat.
     */
    public function ajaxActivityLogViewer(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->activityLog === null) {
            return $this->jsonResponse(false, ['message' => 'Activity log is not enabled.']);
        }

        $logManager = $this->activityLog;
        $loggedTables = $logManager->getLoggedTables();
        $tables = array_column($loggedTables, 'table_name');

        $result = $logManager->getLogs([], 1, 50);

        $data = [
            'crudId'    => $this->crudId,
            'logs'      => $result['logs'],
            'total'     => $result['total'],
            'page'      => 1,
            'perPage'   => 50,
            'tables'    => $tables,
            'actions'   => ['insert', 'update', 'delete', 'restore', 'import'],
            'sortField' => 'created_at',
            'sortDir'   => 'DESC',
        ];

        $html = $this->theme->renderActivityLogViewer($data);

        return $this->jsonResponse(true, ['html' => $html]);
    }

    /**
     * AJAX: Mengembalikan HTML tabel activity log yang difilter & dipaginasi.
     *
     * Dipanggil ketika pengguna mengganti halaman, menerapkan filter, atau mengubah urutan.
     */
    public function ajaxActivityLogData(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->activityLog === null) {
            return $this->jsonResponse(false, ['message' => 'Activity log is not enabled.']);
        }

        $request = Services::request();

        $filters = [];
        $tableName = $request->getPost('table_name');
        if (!empty($tableName)) {
            $filters['table_name'] = $tableName;
        }
        $action = $request->getPost('action');
        if (!empty($action)) {
            $filters['action'] = $action;
        }
        $dateFrom = $request->getPost('date_from');
        if (!empty($dateFrom)) {
            $filters['date_from'] = $dateFrom;
        }
        $dateTo = $request->getPost('date_to');
        if (!empty($dateTo)) {
            $filters['date_to'] = $dateTo;
        }

        $page = (int) ($request->getPost('page') ?? 1);
        $perPage = (int) ($request->getPost('perPage') ?? 50);
        $sortField = $request->getPost('sort_field') ?? 'created_at';
        $sortDir = $request->getPost('sort_dir') ?? 'DESC';

        $result = $this->activityLog->getLogs(
            $filters,
            max(1, $page),
            max(10, min(100, $perPage)),
            $sortField,
            $sortDir
        );

        $data = [
            'crudId'    => $this->crudId,
            'logs'      => $result['logs'],
            'total'     => $result['total'],
            'page'      => $page,
            'perPage'   => $perPage,
            'sortField' => $sortField,
            'sortDir'   => $sortDir,
        ];

        $html = $this->theme->renderActivityLogTable($data);

        return $this->jsonResponse(true, [
            'html'  => $html,
            'total' => $result['total'],
            'page'  => $page,
        ]);
    }

    /**
     * AJAX: Mengembalikan HTML modal detail activity log.
     *
     * Menampilkan perbandingan nilai lama vs baru untuk entri log tertentu.
     */
    public function ajaxActivityLogDetail(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->activityLog === null) {
            return $this->jsonResponse(false, ['message' => 'Activity log is not enabled.']);
        }

        $request = Services::request();
        $logId = $request->getPost('log_id');

        if (empty($logId)) {
            return $this->jsonResponse(false, ['message' => 'Log ID is required.']);
        }

        $log = $this->activityLog->getLogById((int) $logId);

        if ($log === null) {
            return $this->jsonResponse(false, ['message' => 'Log not found.']);
        }

        $html = $this->theme->renderActivityLogDetail($log);

        return $this->jsonResponse(true, ['html' => $html]);
    }

    /**
     * Handler AJAX: Mengembalikan record yang diformat sebagai event FullCalendar.
     *
     * GET /?gc_action=calendar_data
     * Mengembalikan JSON dengan array events yang kompatibel dengan FullCalendar.
     */
    /**
     * Melepaskan kunci record (dipanggil saat pengguna membatalkan/menutup form edit).
     *
     * Menerima POST: id
     */
    private function ajaxReleaseLock(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->recordLockManager === null) {
            return $this->jsonResponse(true, []);
        }

        $request = Services::request();
        $id = $request->getPost('id');

        if (empty($id)) {
            return $this->jsonResponse(false, ['message' => 'Missing record ID.']);
        }

        $this->recordLockManager->releaseLock($this->table, $id);

        return $this->jsonResponse(true, []);
    }

    private function ajaxCalendarData(): ResponseInterface
    {
        $this->ensureInitialized();

        if ($this->calendarField === null) {
            return $this->jsonResponse(false, ['message' => 'Calendar view is not enabled.']);
        }

        $request = Services::request();

        $search    = $request->getGet('search') ?? $request->getPost('search') ?? null;
        $filtersJson = $request->getGet('filters') ?? $request->getPost('filters') ?? '{}';
        $filters   = json_decode($filtersJson, true) ?? [];
        $advancedFilters = json_decode($request->getGet('advanced_filters') ?? '[]', true) ?: [];

        // Selesaikan kolom menggunakan logika yang sama dengan tabel
        $columns = $this->resolveColumns();

        // Tentukan field judul
        $titleField = $this->calendarTitleField ?? $this->primaryKey;

        // Bangun data daftar dengan semua record (tanpa batas paginasi untuk kalender)
        $listData = $this->buildListData(1, $search, 999999, null, null, $filters, $advancedFilters, $columns);

        $events = [];
        foreach ($listData['records'] as $record) {
            $dateValue = $record[$this->calendarField] ?? null;
            if ($dateValue === null || $dateValue === '') {
                continue;
            }

            $title = $record[$titleField] ?? ('#' . ($record[$this->primaryKey] ?? ''));

            $event = [
                'id'    => (string) ($record[$this->primaryKey] ?? ''),
                'title' => $title,
                'start' => $dateValue,
            ];

            // Coba deteksi apakah menyertakan waktu (bukan hanya tanggal)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
                // Hanya tanggal — tandai sebagai all-day
                $event['allDay'] = true;
            }

            $events[] = $event;
        }

        return $this->jsonResponse(true, ['events' => $events]);
    }

    /**
     * Membuat respons JSON.
     */
    private function jsonResponse(bool $success, array $data = []): ResponseInterface
    {
        $response = Services::response();
        return $response
            ->setContentType('application/json')
            ->setJSON(array_merge(['success' => $success], $data));
    }

    // ======== REST API Response Helpers ========

    /**
     * Membuat respons sukses terstandarisasi untuk mode REST API.
     *
     * @param mixed       $data       Muatan respons (null, array, atau object)
     * @param string      $message    Pesan sukses opsional
     * @param int         $statusCode Kode status HTTP (200, 201, dll.)
     * @param array       $extra      Kunci tingkat atas tambahan (misal pagination)
     * @return ResponseInterface
     */
    private function apiResponse(mixed $data = null, string $message = '', int $statusCode = 200, array $extra = []): ResponseInterface
    {
        $body = array_merge([
            'data'    => $data,
            'message' => $message,
        ], $extra);

        // Hapus kunci message jika kosong
        if (empty($message)) {
            unset($body['message']);
        }

        // Hapus kunci data jika null
        if ($data === null) {
            unset($body['data']);
        }

        return Services::response()
            ->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setJSON($body);
    }

    /**
     * Membuat respons error terstandarisasi untuk mode REST API.
     *
     * @param string $message    Deskripsi error
     * @param int    $statusCode Kode status HTTP (400, 403, 404, 422, 500)
     * @param array  $errors     Error validasi tingkat field opsional
     * @return ResponseInterface
     */
    private function apiError(string $message, int $statusCode = 400, array $errors = []): ResponseInterface
    {
        $body = ['message' => $message];

        if (!empty($errors)) {
            $body['errors'] = $errors;
        }

        return Services::response()
            ->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setJSON($body);
    }

    // ======== Repeater Field Helpers ========

    /**
     * Memproses data repeater sebelum penyimpanan.
     * Untuk preset JSON: json_encode array.
     * Untuk preset HasMany: hapus dari data (ditangani terpisah).
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
                // Simpan sementara untuk diproses nanti
                $this->repeaterUnsaved[$field] = $data[$field];
                unset($data[$field]);
            }
        }
    }

    /** @var array<string, array> Penyimpanan sementara untuk data repeater hasMany selama penyimpanan */
    private array $repeaterUnsaved = [];

    /**
     * Menyimpan data repeater hasMany setelah record utama disimpan.
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

        // Hapus baris terkait yang ada
        $this->model->deleteRelatedRows($relatedTable, $foreignKey, $parentId);

        // Masukkan baris baru
        foreach ($items as $item) {
            $item[$foreignKey] = $parentId;
            $this->model->insertRelatedRow($relatedTable, $item);
        }
    }

    /**
     * Memproses data repeater setelah record utama disimpan.
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
