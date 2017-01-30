<?php

namespace App\Ninja\Import\CSV;

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
            return [
                'amount' => $this->getFloat($data, 'amount'),
                'vendor_id' => isset($data->vendor) ? $this->getVendorId($data->vendor) : null,
                'client_id' => isset($data->client) ? $this->getClientId($data->client) : null,
                'expense_date' => isset($data->expense_date) ? date('Y-m-d', strtotime($data->expense_date)) : null,
                'public_notes' => $this->getString($data, 'public_notes'),
                'expense_category_id' => isset($data->expense_category) ? $this->getExpenseCategoryId($data->expense_category) : null,
            ];
        });
    }
}
