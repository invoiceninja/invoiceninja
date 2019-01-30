<?php

namespace App\Ninja\Import\Zoho;

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
        if (! $this->getClientId($data->customer_name)) {
            return false;
        }

        if ($this->hasInvoice($data->invoice_number)) {
            return false;
        }

        return new Item($data, function ($data) {
            $invoice = [
                'client_id' => $this->getClientId($data->customer_name),
                'invoice_number' => $this->getInvoiceNumber($data->invoice_number),
                'paid' => (float) $data->total - (float) $data->balance,
                'po_number' => $this->getString($data, 'purchaseorder'),
                'due_date_sql' => $data->due_date,
                'invoice_date_sql' => $data->invoice_date,
                'custom_value1' => (float) $data->latefee_amount + (float) $data->adjustment + (float) $data->shipping_charge,
                'custom_taxes1' => false,
                'invoice_items' => [
                    [
                        'product_key' => $this->getString($data, 'item_name'),
                        'notes' => $this->getString($data, 'item_desc'),
                        'cost' => (float) $data->item_price,
                        'qty' => (float) $data->quantity,
                        'tax_name1' => (float) $data->item_tax1 ? trans('texts.tax') : '',
                        'tax_rate1' => (float) $data->item_tax1,
                        'tax_name2' => (float) $data->item_tax2 ? trans('texts.tax') : '',
                        'tax_rate2' => (float) $data->item_tax2,
                    ],
                ],
            ];

            // we don't support line item discounts so we need to include
            // the discount as a separate line item
            if ((float) $data->discount_amount) {
                $invoice['invoice_items'][] = [
                    'product_key' => '',
                    'notes' => trans('texts.discount'),
                    'cost' => (float) $data->discount_amount * -1,
                    'qty' => 1,
                    'tax_name1' => (float) $data->item_tax1 ? trans('texts.tax') : '',
                    'tax_rate1' => (float) $data->item_tax1,
                    'tax_name2' => (float) $data->item_tax2 ? trans('texts.tax') : '',
                    'tax_rate2' => (float) $data->item_tax2,
                ];
            }

            return $invoice;
        });
    }
}
