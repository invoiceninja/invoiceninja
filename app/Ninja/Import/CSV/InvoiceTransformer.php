<?php namespace App\Ninja\Import\CSV;

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
        if ( ! $this->getClientId($data->name)) {
            return false;
        }

        if (isset($data->invoice_number) && $this->hasInvoice($data->invoice_number)) {
            return false;
        }

        return new Item($data, function ($data) {
            return [
                'client_id' => $this->getClientId($data->name),
                'invoice_number' => isset($data->invoice_number) ? $this->getInvoiceNumber($data->invoice_number) : null,
                'paid' => isset($data->paid) ? (float) $data->paid : null,
                'po_number' => $this->getString($data, 'po_number'),
                'terms' => $this->getString($data, 'terms'),
                'public_notes' => $this->getString($data, 'public_notes'),
                'invoice_date_sql' => isset($data->invoice_date) ? $data->invoice_date : null,
                'invoice_items' => [
                    [
                        'product_key' => '',
                        'notes' => $this->getString($data, 'notes'),
                        'cost' => isset($data->amount) ? (float) $data->amount : null,
                        'qty' => 1,
                    ]
                ],
            ];
        });
    }
}