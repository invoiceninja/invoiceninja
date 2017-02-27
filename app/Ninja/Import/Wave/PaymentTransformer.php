<?php

namespace App\Ninja\Import\Wave;

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
     * @return bool|Item
     */
    public function transform($data)
    {
        if (! $this->getInvoiceClientId($data->invoice_num)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'amount' => (float) $data->amount,
                'payment_date_sql' => $this->getDate($data, 'payment_date'),
                'client_id' => $this->getInvoiceClientId($data->invoice_num),
                'invoice_id' => $this->getInvoiceId($data->invoice_num),
            ];
        });
    }
}
