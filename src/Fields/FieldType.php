<?php

declare(strict_types=1);

namespace GroceryCrud\Fields;

enum FieldType: string
{
    case TEXT       = 'text';
    case INTEGER    = 'integer';
    case NUMERIC    = 'numeric';
    case TEXTAREA   = 'textarea';
    case EMAIL      = 'email';
    case PASSWORD   = 'password';
    case DROPDOWN   = 'dropdown';
    case ENUM       = 'enum';
    case SET        = 'set';
    case DATE       = 'date';
    case DATETIME   = 'datetime';
    case TIME       = 'time';
    case TRUE_FALSE = 'true_false';
    case IMAGE      = 'image';
    case FILE       = 'file';
    case HIDDEN     = 'hidden';
    case COLOR      = 'color';
    case URL        = 'url';
    case PHONE      = 'phone';
    case READ_ONLY  = 'read_only';
    case RELATION   = 'relation';

    /**
     * Detect field type from database column info.
     */
    public static function detect(string $dbType, ?array $values = null): self
    {
        $type = strtolower($dbType);

        // Integer types
        if (in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'integer', 'serial'], true)) {
            return $type === 'tinyint' && $values !== null && count($values) <= 2
                ? self::TRUE_FALSE
                : self::INTEGER;
        }

        // Decimal / float types
        if (in_array($type, ['float', 'double', 'decimal', 'real', 'numeric'], true)) {
            return self::NUMERIC;
        }

        // Text types
        if (in_array($type, ['char', 'varchar'], true)) {
            return self::TEXT;
        }

        if (in_array($type, ['tinytext', 'text', 'mediumtext', 'longtext'], true)) {
            return self::TEXTAREA;
        }

        // Date/Time types
        if ($type === 'date') {
            return self::DATE;
        }
        if (in_array($type, ['datetime', 'timestamp'], true)) {
            return self::DATETIME;
        }
        if ($type === 'time') {
            return self::TIME;
        }
        if ($type === 'year') {
            return self::INTEGER;
        }

        // Enum / Set
        if ($type === 'enum') {
            return self::ENUM;
        }
        if ($type === 'set') {
            return self::SET;
        }

        // Boolean
        if (in_array($type, ['bool', 'boolean', 'bit'], true)) {
            return self::TRUE_FALSE;
        }

        // Blob
        if (in_array($type, ['tinyblob', 'blob', 'mediumblob', 'longblob', 'binary', 'varbinary'], true)) {
            return self::FILE;
        }

        // JSON
        if ($type === 'json') {
            return self::TEXTAREA;
        }

        return self::TEXT;
    }
}
