<?php namespace App\Ninja\Import\FreshBooks;

use League\Fractal\TransformerAbstract;
use App\Models\Country;
use League\Fractal\Resource\Item;

class ClientTransformer extends TransformerAbstract
{
    public function transform($data, $maps)
    {
        if (isset($maps[ENTITY_CLIENT][$data->organization])) {
            return false;
        }

        if (isset($maps['countries'][$data->country])) {
            $data->country_id = $maps['countries'][$data->country];
        }

        return new Item($data, function ($data) use ($maps) {
            return [
                'name' => $data->organization,
                'work_phone' => $data->busphone,
                'address1' => $data->street,
                'address2' => $data->street2,
                'city' => $data->city,
                'state' => $data->province,
                'postal_code' => $data->postalcode,
                'private_notes' => $data->notes,
                'contacts' => [
                    [
                        'first_name' => $data->firstname,
                        'last_name' => $data->lastname,
                        'email' => $data->email,
                        'phone' => $data->mobphone ?: $data->homephone,
                    ],
                ],
                'country_id' => $data->country_id,
            ];
        });
    }
}
