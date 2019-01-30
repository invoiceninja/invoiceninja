<?php

namespace App\Ninja\Import\CSV;

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
        if (isset($data->name) && $this->hasVendor($data->name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'name'),
                'work_phone' => $this->getString($data, 'work_phone'),
                'address1' => $this->getString($data, 'address1'),
                'city' => $this->getString($data, 'city'),
                'state' => $this->getString($data, 'state'),
                'postal_code' => $this->getString($data, 'postal_code'),
                'private_notes' => $this->getString($data, 'notes'),
                'vendor_contacts' => [
                    [
                        'first_name' => $this->getString($data, 'contact_first_name'),
                        'last_name' => $this->getString($data, 'contact_last_name'),
                        'email' => $this->getString($data, 'contact_email'),
                        'phone' => $this->getString($data, 'contact_phone'),
                    ],
                ],
                'country_id' => isset($data->country) ? $this->getCountryId($data->country) : null,
            ];
        });
    }
}
