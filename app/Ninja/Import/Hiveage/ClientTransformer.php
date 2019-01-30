<?php

namespace App\Ninja\Import\Hiveage;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class ClientTransformer.
 */
class ClientTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if ($this->hasClient($data->name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'name'),
                'contacts' => [
                    [
                        'first_name' => $this->getFirstName($data->primary_contact),
                        'last_name' => $this->getLastName($data->primary_contactk),
                        'email' => $this->getString($data, 'business_email'),
                    ],
                ],
                'address1' => $this->getString($data, 'address_1'),
                'address2' => $this->getString($data, 'address_2'),
                'city' => $this->getString($data, 'city'),
                'state' => $this->getString($data, 'state_name'),
                'postal_code' => $this->getString($data, 'zip_code'),
                'work_phone' => $this->getString($data, 'phone'),
                'website' => $this->getString($data, 'website'),
                'country_id' => $this->getCountryId($data->country),
            ];
        });
    }
}
