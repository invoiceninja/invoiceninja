<?php

namespace App\Ninja\Import\CSV;

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
                'amount' => $this->getFloat($data, 'paid'),
                'payment_date_sql' => isset($data->invoice_date) ? $data->invoice_date : null,
				'private_notes' => $this->getString($data, 'private_notes'),
                'client_id' => $data->client_id,
                'invoice_id' => $data->invoice_id,
				'transaction_reference' => $this->getString($data, 'transaction_reference'),
            ];
        });
    }
}
