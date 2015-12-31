<?php namespace App\Ninja\Import\Wave;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class PaymentTransformer extends BaseTransformer
{
    public function transform($data, $maps)
    {
        if ( ! $this->getInvoiceClientId($data->invoice_num)) {
            return false;
        }
        
        return new Item($data, function ($data) use ($maps) {
            return [
                'amount' => (float) $data->amount,
                'payment_date_sql' => $this->getDate($data->payment_date),
                'client_id' => $this->getInvoiceClientId($data->invoice_num),
                'invoice_id' => $this->getInvoiceId($data->invoice_num),
            ];
        });
    }
}