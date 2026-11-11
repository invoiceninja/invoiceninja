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

namespace App\Services\Report\TaxPeriod;

use App\Models\Product;

/**
 * Derives a single classification string from an invoice line item using
 * its origin (task / expense), its product tax category (`tax_id`) and
 * its type marker (`type_id`).
 */
final class LineClassifier
{
    public const PRODUCT  = 'product';
    public const SERVICE  = 'service';
    public const LABOR    = 'labor';
    public const EXPENSE  = 'expense';
    public const DIGITAL  = 'digital';
    public const SHIPPING = 'shipping';
    public const EXEMPT   = 'exempt';
    public const FEE      = 'fee';

    public static function classify(object $line_item): string
    {
        $expense_id = self::stringProp($line_item, 'expense_id');
        if ($expense_id !== '' && $expense_id !== '0') {
            return self::EXPENSE;
        }

        $task_id = self::stringProp($line_item, 'task_id');
        if ($task_id !== '' && $task_id !== '0') {
            return self::LABOR;
        }

        $tax_id = self::stringProp($line_item, 'tax_id');
        if ($tax_id !== '') {
            switch ((int) $tax_id) {
                case Product::PRODUCT_TYPE_SERVICE:
                    return self::SERVICE;
                case Product::PRODUCT_TYPE_DIGITAL:
                    return self::DIGITAL;
                case Product::PRODUCT_TYPE_SHIPPING:
                    return self::SHIPPING;
                case Product::PRODUCT_TYPE_EXEMPT:
                case Product::PRODUCT_TYPE_ZERO_RATED:
                case Product::PRODUCT_TYPE_REVERSE_TAX:
                case Product::PRODUCT_TYPE_REDUCED_TAX:
                case Product::PRODUCT_INTRA_COMMUNITY:
                    return self::EXEMPT;
                case Product::PRODUCT_TYPE_PHYSICAL:
                    return self::PRODUCT;
            }
        }

        $type_id = self::stringProp($line_item, 'type_id');
        switch ($type_id) {
            case '2':
                return self::SERVICE;
            case '3':
            case '4':
            case '5':
                return self::FEE;
            case '6':
                return self::EXPENSE;
            default:
                return self::PRODUCT;
        }
    }

    private static function stringProp(object $line_item, string $name): string
    {
        if (!property_exists($line_item, $name)) {
            return '';
        }

        $value = $line_item->{$name};

        if ($value === null || $value === false) {
            return '';
        }

        return (string) $value;
    }
}
