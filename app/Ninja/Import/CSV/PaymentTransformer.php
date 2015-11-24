<?php namespace App\Ninja\Import\CSV;

use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Item;

class PaymentTransformer extends TransformerAbstract
{
    public function transform($data, $maps)
    {
        return new Item($data, function ($data) use ($maps) {
            return [
                'amount' => $data->paid,
                'payment_date_sql' => isset($data->invoice_date) ? $data->invoice_date : null,
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}