<?php

namespace App\Exceptions;

use App\Services\Quickbooks\TaxCodeComponentKey;
use Exception;

class QuickbooksMissingCompositeTaxCode extends Exception
{
    /**
     * @param  array<int, string>  $componentKeys
     */
    public static function forComponentKeys(array $componentKeys): self
    {
        return new self('QuickBooks requires a composite TaxCode for combined taxes (' . implode(', ', $componentKeys) . '). Create or sync the matching QuickBooks tax code, then retry.');
    }

    /**
     * @param  array<int, array{name: string, rate: float}>  $components
     */
    public static function forComponents(array $components): self
    {
        $labels = array_map(fn (array $component): string => self::componentLabel($component), $components);

        return new self('QuickBooks requires a composite TaxCode for combined taxes (' . implode(' + ', $labels) . '). Create or sync the matching QuickBooks tax code, then retry.');
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