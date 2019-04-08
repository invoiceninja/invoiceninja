<?php

namespace App\Ninja\Import\CSV;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class ContactTransformer.
 */
class ContactTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return Item
     */
    public function transform($data)
    {
        return new Item($data, function ($data) {
            return [
				'first_name' => $data->first_name ?: '',
				'last_name' => $data->last_name ?: '',
				'email' => $data->email ?: '',
				'phone' => $data->phone ?: '',
				'send_invoice' => $data->send_invoice ?: False,
				'custom_value1' => $data->custom_value1 ?: '',
				'custom_value2' => $data->custom_value2 ?: '',
            ];
        });
    }
}
