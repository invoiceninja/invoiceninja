<?php namespace App\Ninja\Import\Invoiceable;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class InvoiceTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ( ! $this->getClientId($data->client_name)) {
            return false;
        }

        if ($this->hasInvoice($data->ref)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->client_name),
                'invoice_number' => $data->ref,
                'po_number' => $data->po_number,
                'invoice_date_sql' => $data->date,
                'due_date_sql' => $data->due_date,
                'invoice_footer' => $data->footer,
                'paid' => (float) $data->paid,
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