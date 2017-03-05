<?php

namespace App\Ninja\Import\Hiveage;

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

        if ($this->hasInvoice($data->statement_no)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->client),
                'invoice_number' => $this->getInvoiceNumber($data->statement_no),
                'paid' => (float) $data->paid_total,
                'invoice_date_sql' => $this->getDate($data, 'date'),
                'due_date_sql' => $this->getDate($data, 'due_date'),
                'invoice_items' => [
                    [
                        'product_key' => '',
                        'notes' => $this->getString($data, 'summary'),
                        'cost' => (float) $data->billed_total,
                        'qty' => 1,
                    ],
                ],
            ];
        });
    }
}
