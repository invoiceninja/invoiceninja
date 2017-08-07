<?php

namespace App\Ninja\Import\CSV;

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
        if (isset($data->name) && $this->hasClient($data->name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'name'),
                'work_phone' => $this->getString($data, 'work_phone'),
                'address1' => $this->getString($data, 'address1'),
                'address2' => $this->getString($data, 'address2'),
                'city' => $this->getString($data, 'city'),
                'state' => $this->getString($data, 'state'),
                'postal_code' => $this->getString($data, 'postal_code'),
                'public_notes' => $this->getString($data, 'public_notes'),
                'private_notes' => $this->getString($data, 'private_notes'),
                'website' => $this->getString($data, 'website'),
                'vat_number' => $this->getString($data, 'vat_number'),
                'id_number' => $this->getString($data, 'id_number'),
                'custom_value1' => $this->getString($data, 'custom1'),
                'custom_value2' => $this->getString($data, 'custom2'),
                'contacts' => [
                    [
                        'first_name' => $this->getString($data, 'contact_first_name'),
                        'last_name' => $this->getString($data, 'contact_last_name'),
                        'email' => $this->getString($data, 'contact_email'),
                        'phone' => $this->getString($data, 'contact_phone'),
                        'custom_value1' => $this->getString($data, 'contact_custom1'),
                        'custom_value2' => $this->getString($data, 'contact_custom2'),
                    ],
                ],
                'country_id' => isset($data->country) ? $this->getCountryId($data->country) : null,
                'currency_code' => $this->getString($data, 'currency'),
            ];
        });
    }
}
