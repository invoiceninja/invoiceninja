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

namespace App\Services\Chart;

use App\Models\Company;
use App\Models\Expense;
use App\Models\Payment;

class ChartService
{
    public Company $company;

    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    public function getCurrencyCodes()
    {

        $currencies = Payment::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->distinct()
            ->get(['currency_id']);

        $expense_currencies = Expense::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->distinct()
            ->get(['expense_currency_id']);


        $currencies = $currencies->merge($expense_currencies)->unique();


    }

}
