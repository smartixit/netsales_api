<?php

namespace App\Support;

/**
 * Prevent spreadsheet applications from evaluating untrusted CSV cells as formulas.
 */
final class CsvValueSanitizer
{
    public static function sanitize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
