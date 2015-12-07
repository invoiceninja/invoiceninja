<?php namespace App\Ninja\Import\Harvest;

use League\Fractal\TransformerAbstract;
use App\Models\Country;
use League\Fractal\Resource\Item;

class ClientTransformer extends TransformerAbstract
{
    public function transform($data, $maps)
    {
        if (isset($maps[ENTITY_CLIENT][$data->client_name])) {
            return false;
        }

        return new Item($data, function ($data) use ($maps) {
            return [
                'name' => $data->client_name,
            ];
        });
    }
}
