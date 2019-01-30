<?php

namespace App\Ninja\Import\Pancake;

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
        if ($this->hasClient($data->company)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'company'),
                'work_phone' => $this->getString($data, 'telephone_number'),
                'website' => $this->getString($data, 'website_url'),
                'private_notes' => $this->getString($data, 'notes'),
                'contacts' => [
                    [
                        'first_name' => $this->getString($data, 'first_name'),
                        'last_name' => $this->getString($data, 'last_name'),
                        'email' => $this->getString($data, 'email'),
                        'phone' => $this->getString($data, 'mobile_number'),
                    ],
                ],
            ];
        });
    }
}
