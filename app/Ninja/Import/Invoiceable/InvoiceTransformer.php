<?php

namespace App\Ninja\Import\Invoiceable;

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
        if (! $this->getClientId($data->client_name)) {
            return false;
        }

        if ($this->hasInvoice($data->ref)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->client_name),
                'invoice_number' => $this->getInvoiceNumber($data->ref),
                'po_number' => $this->getString($data, 'po_number'),
                'invoice_date_sql' => $data->date,
                'due_date_sql' => $data->due_date,
                'invoice_footer' => $this->getString($data, 'footer'),
                'paid' => (float) $data->paid,
                'invoice_items' => [
                    [
                        'product_key' => '',
                        'notes' => $this->getString($data, 'description'),
                        'cost' => (float) $data->total,
                        'qty' => 1,
                    ],
                ],
            ];
        });
    }
}
