<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Factory;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Expense;
use Illuminate\Support\Facades\Log;

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
        $expense->expense_date = null;
        $expense->payment_date = null;

        return $expense;
    }
}
