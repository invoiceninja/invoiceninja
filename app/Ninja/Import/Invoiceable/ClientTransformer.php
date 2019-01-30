<?php

namespace App\Ninja\Import\Invoiceable;

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
        if ($this->hasClient($data->client_name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'client_name'),
                'work_phone' => $this->getString($data, 'tel'),
                'website' => $this->getString($data, 'website'),
                'address1' => $this->getString($data, 'address'),
                'city' => $this->getString($data, 'city'),
                'state' => $this->getString($data, 'state'),
                'postal_code' => $this->getString($data, 'postcode'),
                'country_id' => $this->getCountryIdBy2($data->country),
                'private_notes' => $this->getString($data, 'notes'),
                'contacts' => [
                    [
                        'email' => $this->getString($data, 'email'),
                        'phone' => $this->getString($data, 'mobile'),
                    ],
                ],
            ];
        });
    }
}
