<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\ProductEquipment;

class ProductEquipmentFactory
{
    public static function create(int $company_id, int $user_id, int $product_id): ProductEquipment
    {
        $productAllocation = new ProductEquipment();

        $productAllocation->company_id = $company_id;
        $productAllocation->user_id = $user_id;
        $productAllocation->product_id = $product_id;

        $productAllocation->custom_value1 = '';
        $productAllocation->custom_value2 = '';
        $productAllocation->custom_value3 = '';
        $productAllocation->custom_value4 = '';
        $productAllocation->public_notes = '';
        $productAllocation->private_notes = '';
        $productAllocation->is_deleted = false;

        return $productAllocation;
    }
}
