<?php namespace App\Ninja\Import\CSV;

use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Item;
use App\Models\Client;

class InvoiceTransformer extends TransformerAbstract
{
    public function transform($data, $maps)
    {
        if (isset($maps[ENTITY_INVOICE][$data->invoice_number])) {
            return false;
        }

        if (isset($maps[ENTITY_CLIENT][$data->name])) {
            $data->client_id = $maps[ENTITY_CLIENT][$data->name];
        } else {
            return false;
        }
        
        return new Item($data, function ($data) use ($maps) {
            return [
                'invoice_number' => isset($data->invoice_number) ? $data->invoice_number : null,
                'paid' => isset($data->paid) ? (float) $data->paid : null,
                'client_id' => (int) $data->client_id,
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