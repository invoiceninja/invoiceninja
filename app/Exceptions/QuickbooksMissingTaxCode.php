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
namespace App\Exceptions;

use App\Services\Quickbooks\TaxCodeComponentKey;
use Exception;
use Throwable;

class QuickbooksMissingTaxCode extends Exception
{
    /**
     * @param  array<string, array<int, array{name: string, rate: float}>>  $componentGroups
     */
    public static function forComponentGroups(array $componentGroups, ?Throwable $previous = null): self
    {
        $labels = array_map(
            fn (array $components): string => self::componentGroupLabel($components),
            array_values($componentGroups)
        );

        return new self('QuickBooks requires a TaxCode for taxes (' . implode(', ', $labels) . '). Create or sync the matching QuickBooks tax code, then retry.', 0, $previous);
    }

    /**
     * @param  array<int, array{name: string, rate: float}>  $components
     */
    public static function forComponents(array $components, ?Throwable $previous = null): self
    {
        return new self('QuickBooks requires a TaxCode for taxes (' . self::componentGroupLabel($components) . '). Create or sync the matching QuickBooks tax code, then retry.', 0, $previous);
    }

    /**
     * @param  array<int, array{name: string, rate: float}>  $components
     */
    private static function componentGroupLabel(array $components): string
    {
        $labels = array_map(fn (array $component): string => self::componentLabel($component), $components);

        return implode(' + ', $labels);
    }

    /**
     * @param  array{name?: string, rate?: float|int|string|null}  $component
     */
    private static function componentLabel(array $component): string
    {
        $name = trim((string) ($component['name'] ?? ''));
        $rate = rtrim(rtrim(TaxCodeComponentKey::formatRate($component['rate'] ?? 0), '0'), '.');

        return trim(($name !== '' ? $name . ' ' : '') . $rate . '%');
    }
}