<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Definitions;

class ProductMap
{
    public static function importable()
    {
        return [
            0 => 'product.product_key',
            1 => 'product.notes',
            2 => 'product.cost',
            3 => 'product.price',
            4 => 'product.quantity',
            5 => 'product.tax_name1',
            6 => 'product.tax_rate1',
            7 => 'product.tax_name2',
            8 => 'product.tax_rate2',
            9 => 'product.tax_name3',
            10 => 'product.tax_rate3',
            11 => 'product.custom_value1',
            12 => 'product.custom_value2',
            13 => 'product.custom_value3',
            14 => 'product.custom_value4',
            15 => 'product.image_url',
            16 => 'product.in_stock_quantity',
            17 => 'product.tax_category',
            18 => 'product.max_quantity',
        ];
    }

    public static function import_keys()
    {
        return [
            0 => 'texts.item',
            1 => 'texts.notes',
            2 => 'texts.cost',
            3 => 'texts.price',
            4 => 'texts.quantity',
            5 => 'texts.tax_name',
            6 => 'texts.tax_rate',
            7 => 'texts.tax_name',
            8 => 'texts.tax_rate',
            9 => 'texts.tax_name',
            10 => 'texts.tax_rate',
            11 => 'texts.custom_value',
            12 => 'texts.custom_value',
            13 => 'texts.custom_value',
            14 => 'texts.custom_value',
            15 => 'texts.image_url',
            16 => 'texts.in_stock_quantity',
            17 => 'texts.tax_category',
            18 => 'texts.max_quantity',
        ];
    }
}
