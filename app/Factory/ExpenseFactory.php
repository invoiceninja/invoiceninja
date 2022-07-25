<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\Expense;

class ExpenseFactory
{
    public static function create(int $company_id, int $user_id) :Expense
    {
        $expense = new Expense();
        $expense->user_id = $user_id;
        $expense->company_id = $company_id;
        $expense->is_deleted = false;
        $expense->should_be_invoiced = false;
        $expense->tax_name1 = '';
        $expense->tax_rate1 = 0;
        $expense->tax_name2 = '';
        $expense->tax_rate2 = 0;
        $expense->tax_name3 = '';
        $expense->tax_rate3 = 0;
        $expense->date = null;
        $expense->payment_date = null;
        $expense->amount = 0;
        $expense->foreign_amount = 0;
        $expense->private_notes = '';
        $expense->public_notes = '';
        $expense->transaction_reference = '';
        $expense->custom_value1 = '';
        $expense->custom_value2 = '';
        $expense->custom_value3 = '';
        $expense->custom_value4 = '';
        $expense->tax_amount1 = 0;
        $expense->tax_amount2 = 0;
        $expense->tax_amount3 = 0;

        return $expense;
    }
}
