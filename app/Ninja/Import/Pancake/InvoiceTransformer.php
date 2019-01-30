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
        $clientName = trim(array_last(explode('-', $data->client)));
        $clientId = $this->getClientId($data->client) ?: $this->getClientId($clientName);

        if (! $clientId) {
            return false;
        }

        if ($this->hasInvoice($data->invoice)) {
            return false;
        }

        if ($data->recurring == 'Yes') {
            return false;
        }

        return new Item($data, function ($data) use ($clientId) {
            return [
                'client_id' => $clientId,
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
                        'notes' => $data->item_1_description ?: '',
                        'cost' => (float) $data->item_1_gross_discount > 0 ? $data->item_1_gross_discount * -1 : $data->item_1_rate,
                        'qty' => $data->item_1_quantity,
                    ],
                    [
                        'product_key' => $data->item_2_gross_discount > 0 ? trans('texts.discount') : $data->item_2_name,
                        'notes' => $data->item_2_description ?: '',
                        'cost' => (float) $data->item_2_gross_discount > 0 ? $data->item_2_gross_discount * -1 : $data->item_2_rate,
                        'qty' => $data->item_2_quantity,
                    ],
                    [
                        'product_key' => $data->item_3_gross_discount > 0 ? trans('texts.discount') : $data->item_3_name,
                        'notes' => $data->item_3_description ?: '',
                        'cost' => (float) $data->item_3_gross_discount > 0 ? $data->item_3_gross_discount * -1 : $data->item_3_rate,
                        'qty' => $data->item_3_quantity,
                    ],
                    [
                        'product_key' => $data->item_4_gross_discount > 0 ? trans('texts.discount') : $data->item_4_name,
                        'notes' => $data->item_4_description ?: '',
                        'cost' => (float) $data->item_4_gross_discount > 0 ? $data->item_4_gross_discount * -1 : $data->item_4_rate,
                        'qty' => $data->item_4_quantity,
                    ],
                    [
                        'product_key' => $data->item_5_gross_discount > 0 ? trans('texts.discount') : $data->item_5_name,
                        'notes' => $data->item_5_description ?: '',
                        'cost' => (float) $data->item_5_gross_discount > 0 ? $data->item_5_gross_discount * -1 : $data->item_5_rate,
                        'qty' => $data->item_5_quantity,
                    ],
                    [
                        'product_key' => $data->item_6_gross_discount > 0 ? trans('texts.discount') : $data->item_6_name,
                        'notes' => $data->item_6_description ?: '',
                        'cost' => (float) $data->item_6_gross_discount > 0 ? $data->item_6_gross_discount * -1 : $data->item_6_rate,
                        'qty' => $data->item_6_quantity,
                    ],
                    [
                        'product_key' => $data->item_7_gross_discount > 0 ? trans('texts.discount') : $data->item_7_name,
                        'notes' => $data->item_7_description ?: '',
                        'cost' => (float) $data->item_7_gross_discount > 0 ? $data->item_7_gross_discount * -1 : $data->item_7_rate,
                        'qty' => $data->item_7_quantity,
                    ],
                    [
                        'product_key' => $data->item_8_gross_discount > 0 ? trans('texts.discount') : $data->item_8_name,
                        'notes' => $data->item_8_description ?: '',
                        'cost' => (float) $data->item_8_gross_discount > 0 ? $data->item_8_gross_discount * -1 : $data->item_8_rate,
                        'qty' => $data->item_8_quantity,
                    ],
                    [
                        'product_key' => $data->item_9_gross_discount > 0 ? trans('texts.discount') : $data->item_9_name,
                        'notes' => $data->item_9_description ?: '',
                        'cost' => (float) $data->item_9_gross_discount > 0 ? $data->item_9_gross_discount * -1 : $data->item_9_rate,
                        'qty' => $data->item_9_quantity,
                    ],
                    [
                        'product_key' => $data->item_10_gross_discount > 0 ? trans('texts.discount') : $data->item_10_name,
                        'notes' => $data->item_10_description ?: '',
                        'cost' => (float) $data->item_10_gross_discount > 0 ? $data->item_10_gross_discount * -1 : $data->item_10_rate,
                        'qty' => $data->item_10_quantity,
                    ],
                    [
                        'product_key' => $data->item_11_gross_discount > 0 ? trans('texts.discount') : $data->item_11_name,
                        'notes' => $data->item_11_description ?: '',
                        'cost' => (float) $data->item_11_gross_discount > 0 ? $data->item_11_gross_discount * -1 : $data->item_11_rate,
                        'qty' => $data->item_11_quantity,
                    ],
                    [
                        'product_key' => $data->item_12_gross_discount > 0 ? trans('texts.discount') : $data->item_12_name,
                        'notes' => $data->item_12_description ?: '',
                        'cost' => (float) $data->item_12_gross_discount > 0 ? $data->item_12_gross_discount * -1 : $data->item_12_rate,
                        'qty' => $data->item_12_quantity,
                    ],
                    [
                        'product_key' => $data->item_13_gross_discount > 0 ? trans('texts.discount') : $data->item_13_name,
                        'notes' => $data->item_13_description ?: '',
                        'cost' => (float) $data->item_13_gross_discount > 0 ? $data->item_13_gross_discount * -1 : $data->item_13_rate,
                        'qty' => $data->item_13_quantity,
                    ],
                    [
                        'product_key' => $data->item_14_gross_discount > 0 ? trans('texts.discount') : $data->item_14_name,
                        'notes' => $data->item_14_description ?: '',
                        'cost' => (float) $data->item_14_gross_discount > 0 ? $data->item_14_gross_discount * -1 : $data->item_14_rate,
                        'qty' => $data->item_14_quantity,
                    ],
                ],
            ];
        });
    }
}
