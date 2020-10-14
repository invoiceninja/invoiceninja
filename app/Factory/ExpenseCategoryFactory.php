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
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\Log;

class ExpenseCategoryFactory
{
    public static function create(int $company_id, int $user_id) :ExpenseCategory
    {
        $expense = new ExpenseCategory();
        $expense->user_id = $user_id;
        $expense->company_id = $company_id;
        $expense->name = '';
        $expense->is_deleted = false;;

        return $expense;
    }
}
