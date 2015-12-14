<?php namespace App\Ninja\Import\Nutcache;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class InvoiceTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ( ! $this->getClientId($data->client)) {
            return false;
        }

        if ($this->hasInvoice($data->document_no)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->client),
                'invoice_number' => $this->getInvoiceNumber($data->document_no),
                'paid' => (float) $data->paid_to_date,
                'po_number' => $data->purchase_order,
                'terms' => $data->terms,
                'public_notes' => $data->notes,
                'invoice_date_sql' => $this->getDate($data->date),
                'due_date_sql' => $this->getDate($data->due_date),
                'invoice_items' => [
                    [
                        'notes' => $data->description,
                        'cost' => (float) $data->total,
                        'qty' => 1,
                    ]
                ],
            ];
        });
    }
}