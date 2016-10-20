<?php namespace App\Ninja\Import\Zoho;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class InvoiceTransformer
 */
class InvoiceTransformer extends BaseTransformer
{
    /**
     * @param $data
     * @return bool|Item
     */
    public function transform($data)
    {
        if ( ! $this->getClientId($data->customer_name)) {
            return false;
        }

        if ($this->hasInvoice($data->invoice_number)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->customer_name),
                'invoice_number' => $this->getInvoiceNumber($data->invoice_number),
                'paid' => (float) $data->total - (float) $data->balance,
                'po_number' => $this->getString($data, 'purchaseorder'),
                'due_date_sql' => $data->due_date,
                'invoice_date_sql' => $data->invoice_date,
                'invoice_items' => [
                    [
                        'product_key' => '',
                        'notes' => $this->getString($data, 'item_desc'),
                        'cost' => (float) $data->total,
                        'qty' => 1,
                    ]
                ],
            ];
        });
    }
}