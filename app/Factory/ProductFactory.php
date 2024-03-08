<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\Product;

class ProductFactory
{
    public static function create(int $company_id, int $user_id): Product
    {
        $product = new Product();
        $product->company_id = $company_id;
        $product->user_id = $user_id;

        $product->product_key = '';
        $product->notes = '';
        $product->cost = 0;
        $product->price = 0;
        $product->quantity = 1;
        $product->tax_name1 = '';
        $product->tax_rate1 = 0;
        $product->tax_name2 = '';
        $product->tax_rate2 = 0;
        $product->custom_value1 = '';
        $product->custom_value2 = '';
        $product->custom_value3 = '';
        $product->custom_value4 = '';
        $product->is_deleted = false;
        $product->tax_id = 1;

        return $product;
    }
}
