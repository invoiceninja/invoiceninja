<?php namespace App\Ninja\Import\CSV;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class ClientTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if (isset($data->name) && $this->hasClient($data->name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => isset($data->name) ? $data->name : null,
                'work_phone' => isset($data->work_phone) ? $data->work_phone : null,
                'address1' => isset($data->address1) ? $data->address1 : null,
                'city' => isset($data->city) ? $data->city : null,
                'state' => isset($data->state) ? $data->state : null,
                'postal_code' => isset($data->postal_code) ? $data->postal_code : null,
                'private_notes' => isset($data->notes) ? $data->notes : null,
                'contacts' => [
                    [
                        'first_name' => isset($data->first_name) ? $data->first_name : null,
                        'last_name' => isset($data->last_name) ? $data->last_name : null,
                        'email' => isset($data->email) ? $data->email : null,
                        'phone' => isset($data->phone) ? $data->phone : null,
                    ],
                ],
                'country_id' => isset($data->country) ? $this->getCountryId($data->country) : null,
            ];
        });
    }
}
