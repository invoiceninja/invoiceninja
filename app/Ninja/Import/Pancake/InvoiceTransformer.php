<?php

namespace App\Ninja\Import\Pancake;

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

        if ($this->hasInvoice($data->invoice)) {
            return false;
        }

        if ($data->recurring == 'Yes') {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->client),
                'invoice_number' => $this->getInvoiceNumber($data->invoice),
                'invoice_date' => ! empty($data->date_of_creation) ? date('Y-m-d', strtotime($data->date_of_creation)) : null,
                'due_date' => ! empty($data->due_date) ? date('Y-m-d', strtotime($data->due_date)) : null,
                'paid' => (float) $data->amount_paid,
                'public_notes' => $this->getString($data, 'notes'),
                'private_notes' => $this->getString($data, 'description'),
                'invoice_date_sql' => $data->create_date,
                'invoice_items' => [
                    [
                        'product_key' => $data->item_1_gross_discount > 0 ? trans('texts.discount') : $data->item_1_name,
                        'notes' => $data->item_1_description,
                        'cost' => (float) $data->item_1_gross_discount > 0 ? $data->item_1_gross_discount * -1 : $data->item_1_rate,
                        'qty' => $data->item_1_quantity,
                    ],
                    [
                        'product_key' => $data->item_2_gross_discount > 0 ? trans('texts.discount') : $data->item_2_name,
                        'notes' => $data->item_2_description,
                        'cost' => (float) $data->item_2_gross_discount > 0 ? $data->item_2_gross_discount * -1 : $data->item_2_rate,
                        'qty' => $data->item_2_quantity,
                    ],
                    [
                        'product_key' => $data->item_3_gross_discount > 0 ? trans('texts.discount') : $data->item_3_name,
                        'notes' => $data->item_3_description,
                        'cost' => (float) $data->item_3_gross_discount > 0 ? $data->item_3_gross_discount * -1 : $data->item_3_rate,
                        'qty' => $data->item_3_quantity,
                    ],
                    [
                        'product_key' => $data->item_4_gross_discount > 0 ? trans('texts.discount') : $data->item_4_name,
                        'notes' => $data->item_4_description,
                        'cost' => (float) $data->item_4_gross_discount > 0 ? $data->item_4_gross_discount * -1 : $data->item_4_rate,
                        'qty' => $data->item_4_quantity,
                    ],
                ],
            ];
        });
    }
}
