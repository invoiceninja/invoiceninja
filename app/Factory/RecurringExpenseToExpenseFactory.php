<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\Expense;
use App\Models\RecurringExpense;

class RecurringExpenseToExpenseFactory
{
    public static function create(RecurringExpense $recurring_expense) :Expense
    {
        $expense = new Expense();
        $expense->user_id = $recurring_expense->user_id;
        $expense->assigned_user_id = $recurring_expense->assigned_user_id;
        $expense->vendor_id = $recurring_expense->vendor_id;
        $expense->invoice_id = $recurring_expense->invoice_id;
        $expense->currency_id = $recurring_expense->currency_id;
        $expense->company_id = $recurring_expense->company_id;
        $expense->bank_id = $recurring_expense->bank_id;
        $expense->exchange_rate = $recurring_expense->exchange_rate;
        $expense->is_deleted = false;
        $expense->should_be_invoiced = $recurring_expense->should_be_invoiced;
        $expense->tax_name1 = $recurring_expense->tax_name1;
        $expense->tax_rate1 = $recurring_expense->tax_rate1;
        $expense->tax_name2 = $recurring_expense->tax_name2;
        $expense->tax_rate2 = $recurring_expense->tax_rate2;
        $expense->tax_name3 = $recurring_expense->tax_name3;
        $expense->tax_rate3 = $recurring_expense->tax_rate3;
        $expense->date = now()->format('Y-m-d');
        $expense->payment_date = $recurring_expense->payment_date;
        $expense->amount = $recurring_expense->amount;
        $expense->foreign_amount = $recurring_expense->foreign_amount ?: 0;
        $expense->private_notes = $recurring_expense->private_notes;
        $expense->public_notes = $recurring_expense->public_notes;
        $expense->transaction_reference = $recurring_expense->transaction_reference;
        $expense->custom_value1 = $recurring_expense->custom_value1;
        $expense->custom_value2 = $recurring_expense->custom_value2;
        $expense->custom_value3 = $recurring_expense->custom_value3;
        $expense->custom_value4 = $recurring_expense->custom_value4;
        $expense->transaction_id = $recurring_expense->transaction_id;
        $expense->category_id = $recurring_expense->category_id;
        $expense->payment_type_id = $recurring_expense->payment_type_id;
        $expense->project_id = $recurring_expense->project_id;
        $expense->invoice_documents = $recurring_expense->invoice_documents;
        $expense->tax_amount1 = $recurring_expense->tax_amount1;
        $expense->tax_amount2 = $recurring_expense->tax_amount2;
        $expense->tax_amount3 = $recurring_expense->tax_amount3;
        $expense->uses_inclusive_taxes = $recurring_expense->uses_inclusive_taxes;
        $expense->calculate_tax_by_amount = $recurring_expense->calculate_tax_by_amount;
        
        return $expense;
    }
}
