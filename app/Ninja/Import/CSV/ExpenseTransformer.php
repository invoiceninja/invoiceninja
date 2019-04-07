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
				'created_at' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
				'updated_at' => isset($data->updated_at) ? date('Y-m-d', strtotime($data->updated_at)) : null,
				'deleted_at' => isset($data->deleted_at) ? date('Y-m-d', strtotime($data->deleted_at)) : null,
				'vendor_id' => isset($data->vendor) ? $this->getVendorId($data->vendor) : null,
				'client_id' => $clientId,
				'amount' => $this->getFloat($data, 'amount'),
				'exchange_rate' => $this->getNumber($data, 'exchange_rate'),
                'expense_date' => isset($data->expense_date) ? date('Y-m-d', strtotime($data->expense_date)) : null,
				'private_notes' => $this->getString($data, 'private_notes'),
                'public_notes' => $this->getString($data, 'public_notes'),
				//'invoice_currency_id' => isset($data->invoice_currency) ? $this->getInvoiceCurrencyId($data->invoice_currency) : null,
				'should_be_invoiced' => $clientId ? true : false,
				//'public_id' => isset($data->expense_public) ? $this->getExpensePublicId($data->expense_public) : null,
				//'transaction_id' => isset($data->expense_transaction) ? $this->getExpenseTransactionyId($data->expense_transaction) : null,
				//'expense_currency_id' => isset($data->expense_currency) ? $this->getExpenseCurrencyId($data->expense_currency) : null,
                'expense_category_id' => isset($data->expense_category) ? $this->getExpenseCategoryId($data->expense_category) : null,
				'tax_name1' => $this->getTaxName($this->getString($data, 'item_tax1')) ?: $this->getProduct($data, 'item_product', 'tax_name1', ''),
                'tax_rate1' => $this->getTaxRate($this->getString($data, 'item_tax1')) ?: $this->getProduct($data, 'item_product', 'tax_rate1', 0),
                'tax_name2' => $this->getTaxName($this->getString($data, 'item_tax2')) ?: $this->getProduct($data, 'item_product', 'tax_name2', ''),
                'tax_rate2' => $this->getTaxRate($this->getString($data, 'item_tax2')) ?: $this->getProduct($data, 'item_product', 'tax_rate2', 0),
                'payment_type_id' => isset($data->payment_type) ? Utils::lookupIdInCache($data->payment_type, 'paymentTypes') : null,
                'payment_date' => isset($data->payment_date) ? date('Y-m-d', strtotime($data->payment_date)) : null,
                'transaction_reference' => $this->getString($data, 'transaction_reference'),
                //'invoice_documents' =>
				//'recurring_expense_id' => isset($data->recurring_expense) ? $this->getRecurringExpenseId($data->recurring_expense) : null,
				'custom_value1' => $this->getString($data, 'custom1'),
                'custom_value2' => $this->getString($data, 'custom2'),
            ];
        });
    }
}
