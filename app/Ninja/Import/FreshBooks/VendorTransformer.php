<?php

namespace App\Ninja\Import\FreshBooks;

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
        if ($this->hasVendor($data->organization)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $data->organization,
                'work_phone' => $data->busphone,
                'address1' => $data->street,
                'address2' => $data->street2,
                'city' => $data->city,
                'state' => $data->province,
                'postal_code' => $data->postalcode,
                'private_notes' => $data->notes,
                'contacts' => [
                    [
                        'first_name' => $data->firstname,
                        'last_name' => $data->lastname,
                        'email' => $data->email,
                        'phone' => $data->mobphone ?: $data->homephone,
                    ],
                ],
                'country_id' => $this->getCountryId($data->country),
            ];
        });
    }
}
