<?php

namespace App\Import\Transformers\Csv;

use App\Import\Transformers\BaseTransformer;

/**
 * Class InvoiceTransformer.
 */
class ExpenseTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|array
     */
    public function transform($data)
    {
        $clientId = isset($data['expense.client']) ? $this->getClientId($data['expense.client']) : null;

        return [
            'company_id'            => $this->maps['company']->id,
            'amount'                => $this->getFloat($data, 'expense.amount'),
            'currency_id'           => $this->getCurrencyByCode($data, 'expense.currency_id'),
            'vendor_id'             => isset($data['expense.vendor']) ? $this->getVendorId($data['expense.vendor']) : null,
            'client_id'             => isset($data['expense.client']) ? $this->getClientId($data['expense.client']) : null,
            'date'		            => isset($data['expense.date']) ? date('Y-m-d', strtotime($data['expense.date'])) : null,
            'public_notes'          => $this->getString($data, 'expense.public_notes'),
            'private_notes'         => $this->getString($data, 'expense.private_notes'),
            'category_id'   		=> isset($data['expense.category']) ? $this->getExpenseCategoryId($data['expense.category']) : null,
            'project_id'            => isset($data['expense.project']) ? $this->getProjectId($data['expense.project']) : null,
            'payment_type_id'       => isset($data['expense.payment_type']) ? $this->getPaymentTypeId($data['expense.payment_type']) : null,
            'payment_date'          => isset($data['expense.payment_date']) ? date('Y-m-d', strtotime($data['expense.payment_date'])) : null,
            'custom_value1'        => $this->getString($data, 'expense.custom_value1'),
            'custom_value2'        => $this->getString($data, 'expense.custom_value2'),
            'custom_value3'        => $this->getString($data, 'expense.custom_value3'),
            'custom_value4'        => $this->getString($data, 'expense.custom_value4'),
            'transaction_reference' => $this->getString($data, 'expense.transaction_reference'),
            'should_be_invoiced'    => $clientId ? true : false,
        ];
    }
}
