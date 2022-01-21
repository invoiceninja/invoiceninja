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



    public function totals($start_date, $end_date) :array
    {
        $data = [];

        $data['currencies'] = $this->getCurrencyCodes();
        $data['revenue'] = $this->getRevenue($start_date, $end_date);
        $data['outstanding'] = $this->getOutstanding($start_date, $end_date);
        $data['expenses'] = $this->getExpenses($start_date, $end_date);

        return $data;
    }    

    public function oustanding($start_date, $end_date)
    {

        $company_currency = (int) $this->company->settings->currency_id;

        $results = \DB::select( \DB::raw("
            SELECT
            sum(invoices.balance) as balance,
            JSON_EXTRACT( settings, '$.currency_id' ) AS currency_id
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.status_id IN (2,3)
            AND invoices.company_id = :company_id
            AND invoices.balance > 0
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            AND (invoices.due_date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        "), ['company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date] );
    
        //return $results;

        //the output here will most likely contain a currency_id = null value - we need to merge this value with the company currency

    }

    private function getRevenue($start_date, $end_date) :array
    {
        $revenue = $this->getRevenueQuery($start_date, $end_date);
        $revenue = $this->parseTotals($revenue);
        $revenue = $this->addCountryCodes($revenue);

        return $revenue;
    }

    private function getOutstanding($start_date, $end_date) :array
    {
        $outstanding = $this->getOutstandingQuery($start_date, $end_date);   
        $outstanding = $this->parseTotals($outstanding);
        $outstanding = $this->addCountryCodes($outstanding);
    
        return $outstanding;
    }

    private function getExpenses($start_date, $end_date) :array
    {
        $expenses = $this->getExpenseQuery($start_date, $end_date);
        $expenses = $this->parseTotals($expenses);
        $expenses = $this->addCountryCodes($expenses);
        return $expenses;
    }

    private function parseTotals($data_set) :array
    {
        /* Find the key where the company currency amount lives*/
        $c_key = array_search($this->company->id , array_column($data_set, 'currency_id')); 

        if(!$c_key)
            return $data_set;

        /* Find the key where null currency_id lives */
        $key = array_search(null , array_column($data_set, 'currency_id')); 

        if(!$key)
            return $data_set;

        $null_currency_amount = $data_set[$key]['amount'];
        unset($data_set[$key]);

        $data_set[$c_key]['amount'] += $null_currency_amount;

        return $data_set;

    }

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
        nlog($currency_id);

        $currency = $currencies->filter(function ($item) use($currency_id) {
            return $item->id == $currency_id;
        })->first();

        if($currency)
            return $currency->code;

        return '';

    }

}
