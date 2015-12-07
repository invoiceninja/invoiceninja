<?php namespace App\Ninja\Import\Harvest;

use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Item;
use DateTime;

class PaymentTransformer extends TransformerAbstract
{
    public function transform($data, $maps)
    {
        return new Item($data, function ($data) use ($maps) {

            $paymentDate = DateTime::createFromFormat('m/d/Y', $data->last_payment_date);

            return [
                'amount' => $data->paid_amount,
                'payment_date_sql' => $paymentDate ? $paymentDate->format('Y-m-d') : null,
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
            ];
        });
    }
}