<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\FranceEReporting;

use InvalidArgumentException;

final class ReportDataValidator
{
    public static function assertNonEmptyString(mixed $value, string $field): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            throw new InvalidArgumentException("{$field} must be a non-empty string.");
        }

        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException("{$field} must be a non-empty string.");
        }

        return $value;
    }

    public static function assertOptionalString(mixed $value, string $field): ?string
    {
        if (is_null($value)) {
            return null;
        }

        return self::assertNonEmptyString($value, $field);
    }

    public static function assertDate(mixed $value, string $field): string
    {
        $value = self::assertNonEmptyString($value, $field);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException("{$field} must use YYYY-MM-DD format.");
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        if (! checkdate($month, $day, $year)) {
            throw new InvalidArgumentException("{$field} must be a valid date.");
        }

        return $value;
    }

    public static function assertTime(mixed $value, string $field): string
    {
        $value = self::assertNonEmptyString($value, $field);

        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value)) {
            throw new InvalidArgumentException("{$field} must use HH:MM:SS format.");
        }

        return $value;
    }

    public static function assertNumeric(mixed $value, string $field): int|float|string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new InvalidArgumentException("{$field} must be numeric.");
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("{$field} must be numeric.");
        }

        return $value;
    }

    public static function numericValue(mixed $value, string $field): int|float
    {
        $value = self::assertNumeric($value, $field);
        $number = (float) $value;

        if (abs($number - (int) $number) < 0.00001) {
            return (int) $number;
        }

        return $number;
    }

    public static function assertPositiveInteger(mixed $value, string $field): int
    {
        if (! is_int($value) && ! ctype_digit((string) $value)) {
            throw new InvalidArgumentException("{$field} must be an integer.");
        }

        $value = (int) $value;

        if ($value < 1) {
            throw new InvalidArgumentException("{$field} must be greater than zero.");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public static function assertArray(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("{$field} must be an array.");
        }

        return $value;
    }

    /**
     * @return array<int, mixed>
     */
    public static function assertList(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("{$field} must be an array.");
        }

        if (! array_is_list($value)) {
            throw new InvalidArgumentException("{$field} must be a list.");
        }

        return $value;
    }
}
