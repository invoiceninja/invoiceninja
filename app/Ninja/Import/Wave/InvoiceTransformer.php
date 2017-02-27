<?php

namespace App\Ninja\Import\Wave;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class InvoiceTransformer.
 */
class InvoiceTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        if (! $this->getClientId($data->customer)) {
            return false;
        }

        if ($this->hasInvoice($data->invoice_num)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->customer),
                'invoice_number' => $this->getInvoiceNumber($data->invoice_num),
                'po_number' => $this->getString($data, 'po_so'),
                'invoice_date_sql' => $this->getDate($data, 'invoice_date'),
                'due_date_sql' => $this->getDate($data, 'due_date'),
                'paid' => 0,
                'invoice_items' => [
                    [
                        'product_key' => $this->getString($data, 'product'),
                        'notes' => $this->getString($data, 'description'),
                        'cost' => (float) $data->amount,
                        'qty' => (float) $data->quantity,
                    ],
                ],
            ];
        });
    }
}
