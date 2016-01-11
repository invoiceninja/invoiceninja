<?php namespace App\Ninja\Import\Harvest;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class ClientTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ($this->hasClient($data->client_name)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $this->getString($data, 'client_name'),
            ];
        });
    }
}
