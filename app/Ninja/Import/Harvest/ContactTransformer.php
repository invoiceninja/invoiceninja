<?php namespace App\Ninja\Import\Harvest;

use League\Fractal\TransformerAbstract;
use App\Models\Country;
use League\Fractal\Resource\Item;

class ContactTransformer extends TransformerAbstract
{
    public function transform($data, $maps)
    {
        if (isset($maps[ENTITY_CLIENT][$data->client])) {
            $data->client_id = $maps[ENTITY_CLIENT][$data->client];
        } else {
            return false;
        }

        return new Item($data, function ($data) use ($maps) {
            return [
                'client_id' => $data->client_id,
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'email' => $data->email,
                'phone' => $data->office_phone ?: $data->mobile_phone,
            ];
        });
    }
}
