<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Factory;

use App\Models\ExpenseCategory;

class ExpenseCategoryFactory
{
    public static function create(int $company_id, int $user_id) :ExpenseCategory
    {
        $expense = new ExpenseCategory();
        $expense->user_id = $user_id;
        $expense->company_id = $company_id;
        $expense->name = '';
        $expense->is_deleted = false;

        return $expense;
    }
}
