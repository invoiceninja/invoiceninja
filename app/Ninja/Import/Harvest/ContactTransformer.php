<?php namespace App\Ninja\Import\Harvest;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class ContactTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ( ! $this->hasClient($data->client)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->client),
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'phone' => $data->office_phone ?: $data->mobile_phone,
            ];
        });
    }
}
