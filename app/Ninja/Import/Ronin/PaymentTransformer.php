<?php

namespace App\Ninja\Import\Ronin;

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
                'amount' => (float) $data->total - (float) $data->balance,
                'payment_date_sql' => $data->date_paid,
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}
