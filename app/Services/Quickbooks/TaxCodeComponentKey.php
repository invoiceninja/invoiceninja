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
namespace App\Services\Quickbooks;

class TaxCodeComponentKey
{
    /**
     * @param  array<int, array{name?: string, rate?: float|int|string|null}>  $components
     */
    public static function fromComponents(array $components): string
    {
        $parts = [];

        foreach ($components as $component) {
            $rate = self::formatRate($component['rate'] ?? 0);

            if ((float) $rate <= 0) {
                continue;
            }

            $parts[] = self::normalizeName((string) ($component['name'] ?? '')) . ':' . $rate;
        }

        sort($parts, SORT_STRING);

        return implode('|', $parts);
    }

    public static function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+\d+(?:\.\d+)?\s*%?$/', '', $name) ?? $name;

        return preg_replace('/\s+/', ' ', trim($name)) ?? $name;
    }

    public static function formatRate(float|int|string|null $rate): string
    {
        return number_format((float) ($rate ?? 0), 4, '.', '');
    }
}