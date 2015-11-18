<?php namespace App\Ninja\Import\FreshBooks;

use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Item;

class PaymentTransformer extends TransformerAbstract
{
    public function transform($data, $maps)
    {
        return new Item($data, function ($data) use ($maps) {
            return [
                'amount' => $data->paid,
                'payment_date_sql' => $data->create_date,
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}