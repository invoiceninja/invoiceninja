<?php namespace App\Ninja\Import\CSV;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class InvoiceTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ( ! $this->getClientId($data->name)) {
            return false;
        }

        if (isset($data->invoice_number) && $this->hasInvoice($data->invoice_number)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'invoice_number' => isset($data->invoice_number) ? $data->invoice_number : null,
                'paid' => isset($data->paid) ? (float) $data->paid : null,
                'client_id' => $this->getClientId($data->name),
                'po_number' => isset($data->po_number) ? $data->po_number : null,
                'terms' => isset($data->terms) ? $data->terms : null,
                'public_notes' => isset($data->notes) ? $data->notes : null,
                'invoice_date_sql' => isset($data->invoice_date) ? $data->invoice_date : null,
                'invoice_items' => [
                    [
                        'notes' => isset($data->notes) ? $data->notes : null,
                        'cost' => isset($data->amount) ? (float) $data->amount : null,
                        'qty' => 1,
                    ]
                ],
            ];
        });
    }
}