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

use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\Chart\ChartQueries;
use Illuminate\Support\Facades\Cache;

class ChartService
{
    use ChartQueries;

    public Company $company;

    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    /**
     * Returns an array of currencies that have
     * transacted with a company
     */
    public function getCurrencyCodes() :array
    {
        /* Get all the distinct client currencies */
        $currencies = Client::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->distinct()
            ->pluck('settings->currency_id as id');

        /* Push the company currency on also */
        $currencies->push((int)$this->company->settings->currency_id);

        /* Add our expense currencies*/
        $expense_currencies = Expense::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->distinct()
            ->pluck('currency_id as id');

        /* Merge and filter by unique */
        $currencies = $currencies->merge($expense_currencies)->unique();

        $cache_currencies = Cache::get('currencies');

        $filtered_currencies = $cache_currencies->whereIn('id', $currencies)->all();

        $final_currencies = [];

        foreach($filtered_currencies as $c_currency)
        {
            $final_currencies[$c_currency['id']] = $c_currency['code'];
        }

        return $final_currencies;

    }

/* Payments */
    public function payments($start_date, $end_date)
    {
        $payments = $this->getPaymentQuery();
    }

/* Payments */

/* Totals */

    public function totals($start_date, $end_date) :array
    {
        $data = [];

        $data['currencies'] = $this->getCurrencyCodes();
        $data['revenue'] = $this->getRevenue($start_date, $end_date);
        $data['outstanding'] = $this->getOutstanding($start_date, $end_date);
        $data['expenses'] = $this->getExpenses($start_date, $end_date);

        return $data;
    }    

    private function getRevenue($start_date, $end_date) :array
    {
        $revenue = $this->getRevenueQuery($start_date, $end_date);
        $revenue = $this->addCountryCodes($revenue);

        return $revenue;
    }

    private function getOutstanding($start_date, $end_date) :array
    {
        $outstanding = $this->getOutstandingQuery($start_date, $end_date);   
        $outstanding = $this->addCountryCodes($outstanding);
    
        return $outstanding;
    }

    private function getExpenses($start_date, $end_date) :array
    {
        $expenses = $this->getExpenseQuery($start_date, $end_date);
        $expenses = $this->addCountryCodes($expenses);

        return $expenses;
    }

/* Totals */

/* Helpers */

    private function addCountryCodes($data_set) :array
    {

        $currencies = Cache::get('currencies');

        foreach($data_set as $key => $value)
        {
            $data_set[$key]->currency_id = str_replace('"', '', $value->currency_id);
            $data_set[$key]->code = $this->getCode($currencies, $data_set[$key]->currency_id); 
        }

        return $data_set;

    }

    private function getCode($currencies, $currency_id) :string
    {
        $currency_id = str_replace('"', '', $currency_id);

        $currency = $currencies->filter(function ($item) use($currency_id) {
            return $item->id == $currency_id;
        })->first();

        if($currency)
            return $currency->code;

        return '';

    }

}
