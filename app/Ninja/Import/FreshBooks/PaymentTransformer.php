<?php

namespace App\Ninja\Import\FreshBooks;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class PaymentTransformer.
 */
class PaymentTransformer extends BaseTransformer
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
                'amount' => $data->paid,
                'payment_date_sql' => $data->create_date,
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}
