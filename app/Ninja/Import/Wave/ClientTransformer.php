<?php

namespace App\Ninja\Import\Wave;

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
        if ($this->hasClient($data->customer_name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'customer_name'),
                'id_number' => $this->getString($data, 'account_number'),
                'work_phone' => $this->getString($data, 'phone'),
                'website' => $this->getString($data, 'website'),
                'address1' => $this->getString($data, 'address_line_1'),
                'address2' => $this->getString($data, 'address_line_2'),
                'city' => $this->getString($data, 'city'),
                'state' => $this->getString($data, 'provincestate'),
                'postal_code' => $this->getString($data, 'postal_codezip_code'),
                'private_notes' => $this->getString($data, 'delivery_instructions'),
                'contacts' => [
                    [
                        'first_name' => $this->getString($data, 'contact_first_name'),
                        'last_name' => $this->getString($data, 'contact_last_name'),
                        'email' => $this->getString($data, 'email'),
                        'phone' => $this->getString($data, 'mobile'),
                    ],
                ],
                'country_id' => $this->getCountryId($data->country),
            ];
        });
    }
}
