<?php namespace App\Ninja\Import\Nutcache;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class PaymentTransformer extends BaseTransformer
{
    public function transform($data, $maps)
    {
        return new Item($data, function ($data) use ($maps) {
            return [
                'amount' => (float) $data->paid_to_date,
                'payment_date_sql' => $this->getDate($data->date),
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}