<?php

namespace App\Ninja\Import\Hiveage;

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
        if ($this->hasVendor($data->name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $data->name,
                'contacts' => [
                    [
                        'first_name' => $this->getFirstName($data->primary_contact),
                        'last_name' => $this->getLastName($data->primary_contactk),
                        'email' => $data->business_email,
                    ],
                ],
                'address1' => $data->address_1,
                'address2' => $data->address_2,
                'city' => $data->city,
                'state' => $data->state_name,
                'postal_code' => $data->zip_code,
                'work_phone' => $data->phone,
                'website' => $data->website,
                'country_id' => $this->getCountryId($data->country),
            ];
        });
    }
}
