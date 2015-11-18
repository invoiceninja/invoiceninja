<?php namespace App\Ninja\Import\FreshBooks;

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

        if (isset($maps[ENTITY_CLIENT][$data->organization])) {
            $data->client_id = $maps[ENTITY_CLIENT][$data->organization];
        } else {
            return false;
        }

        return new Item($data, function ($data) use ($maps) {
            return [
                'invoice_number' => $data->invoice_number,
                'paid' => (float) $data->paid,
                'client_id' => (int) $data->client_id,
                'po_number' => $data->po_number,
                'terms' => $data->terms,
                'public_notes' => $data->notes,
                'invoice_date_sql' => $data->create_date,
                'invoice_items' => [
                    [
                        'notes' => $data->notes,
                        'cost' => (float) $data->amount,
                        'qty' => 1,
                    ]
                ],
            ];
        });
    }
}