<?php

namespace App\Ninja\Import\FreshBooks;

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
        if (! $this->getClientId($data->organization)) {
            return false;
        }

        if ($this->hasInvoice($data->invoice_number)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->organization),
                'invoice_number' => $this->getInvoiceNumber($data->invoice_number),
                'paid' => (float) $data->paid,
                'po_number' => $this->getString($data, 'po_number'),
                'terms' => $this->getString($data, 'terms'),
                'invoice_date_sql' => $data->create_date,
                'invoice_items' => [
                    [
                        'product_key' => '',
                        'notes' => $this->getString($data, 'notes'),
                        'cost' => (float) $data->amount,
                        'qty' => 1,
                    ],
                ],
            ];
        });
    }
}
