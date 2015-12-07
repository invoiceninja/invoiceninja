<?php namespace App\Ninja\Import\Harvest;

use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Item;
use App\Models\Client;
use DateTime;

class InvoiceTransformer extends TransformerAbstract
{
    public function transform($data, $maps)
    {
        if (isset($maps[ENTITY_INVOICE][$data->id])) {
            return false;
        }

        if (isset($maps[ENTITY_CLIENT][$data->client])) {
            $data->client_id = $maps[ENTITY_CLIENT][$data->client];
        } else {
            return false;
        }

        return new Item($data, function ($data) use ($maps) {

            $invoiceDate = DateTime::createFromFormat('m/d/Y', $data->issue_date);

            return [
                'invoice_number' => $data->id,
                'paid' => (float) $data->paid_amount,
                'client_id' => (int) $data->client_id,
                'po_number' => $data->po_number,
                'invoice_date_sql' => $invoiceDate->format('Y-m-d'),
                'tax_rate' => $data->tax ?: null,
                'tax_name' => $data->tax ? trans('texts.tax') : null,
                'invoice_items' => [
                    [
                        'notes' => $data->subject,
                        'cost' => (float) $data->invoice_amount,
                        'qty' => 1,
                    ]
                ],
            ];
        });
    }
}