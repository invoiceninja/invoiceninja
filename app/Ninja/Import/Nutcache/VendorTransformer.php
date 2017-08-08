<?php

namespace App\Ninja\Import\Nutcache;

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
                'city' => isset($data->city) ? $data->city : '',
                'state' => isset($data->city) ? $data->stateprovince : '',
                'id_number' => isset($data->registration_number) ? $data->registration_number : '',
                'postal_code' => isset($data->postalzip_code) ? $data->postalzip_code : '',
                'private_notes' => isset($data->notes) ? $data->notes : '',
                'work_phone' => isset($data->phone) ? $data->phone : '',
                'contacts' => [
                    [
                        'first_name' => isset($data->contact_name) ? $this->getFirstName($data->contact_name) : '',
                        'last_name' => isset($data->contact_name) ? $this->getLastName($data->contact_name) : '',
                        'email' => $data->email,
                        'phone' => isset($data->mobile) ? $data->mobile : '',
                    ],
                ],
                'country_id' => isset($data->country) ? $this->getCountryId($data->country) : null,
            ];
        });
    }
}
