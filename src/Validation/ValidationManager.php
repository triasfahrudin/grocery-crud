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
     * Set validation rules for a field.
     *
     * @param string   $field
     * @param string   $rules  CI4 validation rules string
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
     * Add a single rule for a field.
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
     * Mark a field as required.
     */
    public function required(string $field, ?string $label = null): void
    {
        $this->setRule($field, 'required', $label);
    }

    /**
     * Mark a field as unique.
     */
    public function unique(string $field, ?string $label = null): void
    {
        $this->setRule($field, "is_unique[{$this->table}.{$field}]", $label);
    }

    /**
     * Mark a field as unique (ignoring a specific record for updates).
     */
    public function uniqueExcept(string $field, mixed $primaryKeyValue, ?string $label = null): void
    {
        $this->setRule(
            $field,
            "is_unique[{$this->table}.{$field},{$this->primaryKey},{$primaryKeyValue}]",
            $label
        );
    }

    /**
     * Get validation rules for all registered fields.
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
     * Validate data against registered rules.
     *
     * @param  array<string, mixed> $data
     * @return array<string, string> Errors keyed by field
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
     * Check if a field has validation rules.
     */
    public function hasRules(string $field): bool
    {
        return isset($this->fieldRules[$field]);
    }
}
