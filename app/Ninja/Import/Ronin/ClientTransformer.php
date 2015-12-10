<?php namespace App\Ninja\Import\Ronin;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class ClientTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ($this->hasClient($data->company)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'name' => $data->company,
                'work_phone' => $data->phone,
                'contacts' => [
                    [
                        'first_name' => $this->getFirstName($data->name),
                        'last_name' => $this->getLastName($data->name),
                        'email' => $data->email,
                    ],
                ],
            ];
        });
    }
}
