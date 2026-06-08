<?php

declare(strict_types=1);

namespace GroceryCrud\Validation;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Validation\Validation;

class ValidationManager
{
    private Validation $validation;
    private BaseConnection $db;
    private string $table;
    private string $primaryKey;

    /** @var array<string, array<int, array{rule: string, label?: string}>> */
    private array $fieldRules = [];

    /** @var array<string, string> */
    private array $fieldLabels = [];

    public function __construct(Validation $validation, BaseConnection $db, string $table, string $primaryKey)
    {
        $this->validation = $validation;
        $this->db = $db;
        $this->table = $table;
        $this->primaryKey = $primaryKey;
    }

    /**
     * Mengatur aturan validasi untuk sebuah field.
     *
     * @param string   $field
     * @param string   $rules  String aturan validasi CI4
     * @param string|null $label
     */
    public function setRules(string $field, string $rules, ?string $label = null): void
    {
        $ruleItems = explode('|', $rules);
        foreach ($ruleItems as $rule) {
            $this->fieldRules[$field][] = [
                'rule'  => $rule,
                'label' => $label,
            ];
        }

        if ($label !== null) {
            $this->fieldLabels[$field] = $label;
        }
    }

    /**
     * Menambahkan satu aturan untuk sebuah field.
     */
    public function setRule(string $field, string $rule, ?string $label = null): void
    {
        $this->fieldRules[$field][] = [
            'rule'  => $rule,
            'label' => $label,
        ];

        if ($label !== null) {
            $this->fieldLabels[$field] = $label;
        }
    }

    /**
     * Menandai sebuah field sebagai wajib diisi.
     */
    public function required(string $field, ?string $label = null): void
    {
        $this->setRule($field, 'required', $label);
    }

    /**
     * Menandai sebuah field sebagai unik.
     */
    public function unique(string $field, ?string $label = null): void
    {
        $this->setRule($field, "is_unique[{$this->table}.{$field}]", $label);
    }

    /**
     * Menandai sebuah field sebagai unik (mengabaikan record tertentu untuk update).
     *
     * Mengganti aturan is_unique yang ada untuk field yang sama untuk menghindari
     * pemeriksaan is_unique duplikat (yang biasa akan menolak nilai record saat ini).
     */
    public function uniqueExcept(string $field, mixed $primaryKeyValue, ?string $label = null): void
    {
        // Hapus aturan is_unique yang ada untuk field ini
        if (isset($this->fieldRules[$field])) {
            $this->fieldRules[$field] = array_values(
                array_filter(
                    $this->fieldRules[$field],
                    fn(array $r): bool => !str_starts_with($r['rule'], 'is_unique[')
                )
            );
        }

        $this->setRule(
            $field,
            "is_unique[{$this->table}.{$field},{$this->primaryKey},{$primaryKeyValue}]",
            $label
        );
    }

    /**
     * Mendapatkan aturan validasi untuk semua field yang terdaftar.
     *
     * @return array<string, string>
     */
    public function getValidationRules(): array
    {
        $rules = [];
        foreach ($this->fieldRules as $field => $ruleSet) {
            $ruleStrings = [];
            foreach ($ruleSet as $rule) {
                $ruleStrings[] = $rule['rule'];
            }
            $rules[$field] = [
                'rules'  => implode('|', $ruleStrings),
                'label'  => $this->fieldLabels[$field] ?? ucfirst($field),
            ];
        }
        return $rules;
    }

    /**
     * Memvalidasi data terhadap aturan yang terdaftar.
     *
     * @param  array<string, mixed> $data
     * @return array<string, string> Error berdasarkan field
     */
    public function validate(array $data): array
    {
        $rules = $this->getValidationRules();
        if (empty($rules)) {
            return [];
        }

        $this->validation->reset();
        $this->validation->setRules($rules);

        if (!$this->validation->run($data)) {
            return $this->validation->getErrors();
        }

        return [];
    }

    /**
     * Menghapus semua aturan validasi untuk sebuah field.
     */
    public function removeRules(string $field): void
    {
        unset($this->fieldRules[$field]);
        unset($this->fieldLabels[$field]);
    }

    /**
     * Memeriksa apakah sebuah field memiliki aturan validasi.
     */
    public function hasRules(string $field): bool
    {
        return isset($this->fieldRules[$field]);
    }

    /**
     * Memvalidasi nilai satu field terhadap aturannya sendiri saja.
     * Berguna untuk pengeditan inline di mana field lain tidak dikirim.
     *
     * @param string       $field            Nama field
     * @param mixed        $value            Nilai field
     * @param string|int|null $primaryKeyValue  Nilai PK record saat ini (untuk dikecualikan dalam is_unique)
     *
     * @return array<string, string> Error berdasarkan field
     */
    public function validateField(string $field, mixed $value, string|int|null $primaryKeyValue = null): array
    {
        $rules = $this->getValidationRules();

        if (!isset($rules[$field])) {
            return [];
        }

        // If PK value is provided, adapt is_unique to exclude current record
        $fieldRules = $rules[$field];
        if ($primaryKeyValue !== null) {
            $fieldRules['rules'] = preg_replace(
                '/is_unique\[([^,]+)\]/',
                'is_unique[$1,' . $this->primaryKey . ',' . $primaryKeyValue . ']',
                $fieldRules['rules']
            );
        }

        $this->validation->reset();
        $this->validation->setRules([$field => $fieldRules]);

        if (!$this->validation->run([$field => $value])) {
            return $this->validation->getErrors();
        }

        return [];
    }
}
