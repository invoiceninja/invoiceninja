<?php

namespace App\Ninja\Import\CSV;

use Utils;
use App\Ninja\Import\BaseTransformer;
use League\Fractal\Resource\Item;

/**
 * Class InvoiceTransformer.
 */
class ExpenseTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        return new Item($data, function ($data) {
            $clientId = isset($data->client) ? $this->getClientId($data->client) : null;

            return [
                'amount' => $this->getFloat($data, 'amount'),
                'vendor_id' => isset($data->vendor) ? $this->getVendorId($data->vendor) : null,
                'client_id' => $clientId,
                'expense_date' => isset($data->expense_date) ? date('Y-m-d', strtotime($data->expense_date)) : null,
                'public_notes' => $this->getString($data, 'public_notes'),
                'private_notes' => $this->getString($data, 'private_notes'),
                'expense_category_id' => isset($data->expense_category) ? $this->getExpenseCategoryId($data->expense_category) : null,
                'payment_type_id' => isset($data->payment_type) ? Utils::lookupIdInCache($data->payment_type, 'paymentTypes') : null,
                'payment_date' => isset($data->payment_date) ? date('Y-m-d', strtotime($data->payment_date)) : null,
                'transaction_reference' => $this->getString($data, 'transaction_reference'),
                'should_be_invoiced' => $clientId ? true : false,
            ];
        });
    }
}
