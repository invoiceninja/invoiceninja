<?php

namespace App\Ninja\Import\Invoiceable;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

// vendor
/**
 * Class VendorTransformer.
 */
class VendorTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if ($this->hasVendor($data->vendor_name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $data->vendor_name,
                'work_phone' => $data->tel,
                'website' => $data->website,
                'address1' => $data->address,
                'city' => $data->city,
                'state' => $data->state,
                'postal_code' => $data->postcode,
                'country_id' => $this->getCountryIdBy2($data->country),
                'private_notes' => $data->notes,
                'contacts' => [
                    [
                        'email' => $data->email,
                        'phone' => $data->mobile,
                    ],
                ],
            ];
        });
    }
}
