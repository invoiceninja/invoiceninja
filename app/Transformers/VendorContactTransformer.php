<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\VendorContact;
use App\Utils\Traits\MakesHash;

/**
 * Class VendorContactTransformer.
 */
class VendorContactTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @param VendorContact $vendor
     *
     * @return array
     */
    public function transform(VendorContact $vendor)
    {
        return [
            'id' => $this->encodePrimaryKey($vendor->id),
            'first_name' => $vendor->first_name ?: '',
            'last_name' => $vendor->last_name ?: '',
            'email' => $vendor->email ?: '',
            'created_at' => (int) $vendor->created_at,
            'updated_at' => (int) $vendor->updated_at,
            'archived_at' => (int) $vendor->deleted_at,
            'is_primary' => (bool) $vendor->is_primary,
            'phone' => $vendor->phone ?: '',
            'custom_value1' => $vendor->custom_value1 ?: '',
            'custom_value2' => $vendor->custom_value2 ?: '',
            'custom_value3' => $vendor->custom_value3 ?: '',
            'custom_value4' => $vendor->custom_value4 ?: '',
            'link' => $vendor->getLoginLink(),
        ];
    }
}
