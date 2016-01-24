<?php namespace App\Ninja\Import\Zoho;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class PaymentTransformer extends BaseTransformer
{
    public function transform($data, $maps)
    {
        return new Item($data, function ($data) use ($maps) {
            return [
                'amount' => (float) $data->total - (float) $data->balance,
                'payment_date_sql' => $data->last_payment_date,
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}