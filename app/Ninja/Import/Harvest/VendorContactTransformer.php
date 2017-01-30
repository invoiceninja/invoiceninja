<?php

namespace App\Ninja\Import\Harvest;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

// vendor
/**
 * Class VendorContactTransformer.
 */
class VendorContactTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if (! $this->hasVendor($data->vendor)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'vendor_id' => $this->getVendorId($data->vendor),
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'phone' => $data->office_phone ?: $data->mobile_phone,
            ];
        });
    }
}
