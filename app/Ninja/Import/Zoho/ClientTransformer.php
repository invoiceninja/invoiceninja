<?php

namespace App\Ninja\Import\Zoho;

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
                'id_number' => $this->getString($data, 'customer_id'),
                'work_phone' => $this->getString($data, 'phone'),
                'address1' => $this->getString($data, 'billing_address'),
                'city' => $this->getString($data, 'billing_city'),
                'state' => $this->getString($data, 'billing_state'),
                'postal_code' => $this->getString($data, 'billing_code'),
                'private_notes' => $this->getString($data, 'notes'),
                'website' => $this->getString($data, 'website'),
                'contacts' => [
                    [
                        'first_name' => $this->getString($data, 'first_name'),
                        'last_name' => $this->getString($data, 'last_name'),
                        'email' => $this->getString($data, 'emailid'),
                        'phone' => $this->getString($data, 'mobilephone'),
                    ],
                ],
                'country_id' => $this->getCountryId($data->billing_country),
            ];
        });
    }
}
