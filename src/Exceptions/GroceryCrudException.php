<?php

declare(strict_types=1);

namespace GroceryCrud\Exceptions;

use RuntimeException;

class GroceryCrudException extends RuntimeException
{
    public static function tableNotSet(): self
    {
        return new self('Table name is not set. Call setTable() before render().');
    }

    public static function invalidAction(string $action): self
    {
        return new self("Invalid action: {$action}");
    }

    public static function invalidRelationConfig(string $field): self
    {
        return new self("Invalid relation configuration for field: {$field}");
    }

    public static function invalidCallback(string $name): self
    {
        return new self("Callback '{$name}' must be a callable or null.");
    }

    public static function uploadFailed(string $field, string $reason): self
    {
        return new self("Upload failed for field '{$field}': {$reason}");
    }

    public static function themeNotFound(string $theme): self
    {
        return new self("Theme '{$theme}' is not registered or does not exist.");
    }

    public static function primaryKeyNotFound(string $table): self
    {
        return new self("Primary key not found for table '{$table}'.");
    }

    public static function invalidField(string $field, string $table): self
    {
        return new self("Field '{$field}' does not exist in table '{$table}'.");
    }

    public static function uploadError(): self
    {
        return new self('File upload error.');
    }

    public static function invalidFileType(): self
    {
        return new self('Invalid file type. Only CSV and Excel (.xlsx) files are allowed.');
    }

    public static function fileTooLarge(): self
    {
        return new self('File size exceeds the limit.');
    }
}
