<?php

namespace App\Ninja\Import\FreshBooks;

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
        if ($this->hasClient($data->organization)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'organization'),
                'work_phone' => $this->getString($data, 'busphone'),
                'address1' => $this->getString($data, 'street'),
                'address2' => $this->getString($data, 'street2'),
                'city' => $this->getString($data, 'city'),
                'state' => $this->getString($data, 'province'),
                'postal_code' => $this->getString($data, 'postalcode'),
                'private_notes' => $this->getString($data, 'notes'),
                'contacts' => [
                    [
                        'first_name' => $this->getString($data, 'firstname'),
                        'last_name' => $this->getString($data, 'lastname'),
                        'email' => $this->getString($data, 'email'),
                        'phone' => $this->getString($data, 'mobphone') ?: $this->getString($data, 'homephone'),
                    ],
                ],
                'country_id' => $this->getCountryId($data->country),
            ];
        });
    }
}
