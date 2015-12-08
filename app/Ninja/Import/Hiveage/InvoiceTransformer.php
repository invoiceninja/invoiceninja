<?php namespace App\Ninja\Import\Hiveage;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

class InvoiceTransformer extends BaseTransformer
{
    public function transform($data)
    {
        if ( ! $this->getClientId($data->client)) {
            return false;
        }

        if ($this->hasInvoice($data->statement_no)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'invoice_number' => $data->statement_no,
                'paid' => (float) $data->paid_total,
                'client_id' => $this->getClientId($data->client),
                'invoice_date_sql' => $this->getDate($data->date),
                'due_date_sql' => $this->getDate($data->due_date),
                'invoice_items' => [
                    [
                        'notes' => $data->summary,
                        'cost' => (float) $data->billed_total,
                        'qty' => 1,
                    ]
                ],
            ];
        });
    }
}