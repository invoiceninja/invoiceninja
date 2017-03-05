<?php

namespace App\Ninja\Import\Ronin;

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

        if ($this->hasInvoice($data->number)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->client),
                'invoice_number' => $this->getInvoiceNumber($data->number),
                'paid' => (float) $data->total - (float) $data->balance,
                'public_notes' => $this->getString($data, 'subject'),
                'invoice_date_sql' => $data->date_sent,
                'due_date_sql' => $data->date_due,
                'invoice_items' => [
                    [
                        'product_key' => '',
                        'notes' => $this->getString($data, 'line_item'),
                        'cost' => (float) $data->total,
                        'qty' => 1,
                    ],
                ],
            ];
        });
    }
}
