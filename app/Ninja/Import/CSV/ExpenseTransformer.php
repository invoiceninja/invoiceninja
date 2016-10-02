<?php namespace App\Ninja\Import\CSV;

use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class InvoiceTransformer
 */
class ExpenseTransformer extends BaseTransformer
{
    /**
     * @param $data
     * @return bool|Item
     */
    public function transform($data)
    {
        return new Item($data, function ($data) {
            return [
                'amount' => isset($data->amount) ? (float) $data->amount : null,
                'vendor_id' => isset($data->vendor) ? $this->getVendorId($data->vendor) : null,
                'expense_date' => isset($data->expense_date) ? date('Y-m-d', strtotime($data->expense_date)) : null,
                'public_notes' => $this->getString($data, 'public_notes'),
            ];
        });

        /*
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
        */

    }
}
