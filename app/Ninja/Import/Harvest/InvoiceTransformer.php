<?php

namespace App\Ninja\Import\Harvest;

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
        if (! $this->getClientId($data->client)) {
            return false;
        }

        if ($this->hasInvoice($data->id)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->client),
                'invoice_number' => $this->getInvoiceNumber($data->id),
                'paid' => (float) $data->paid_amount,
                'po_number' => $this->getString($data, 'po_number'),
                'invoice_date_sql' => $this->getDate($data, 'issue_date'),
                'invoice_items' => [
                    [
                        'product_key' => '',
                        'notes' => $this->getString($data, 'subject'),
                        'cost' => (float) $data->invoice_amount,
                        'qty' => 1,
                    ],
                ],
            ];
        });
    }
}
