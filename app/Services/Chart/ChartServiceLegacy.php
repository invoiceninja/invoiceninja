<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Chart;

use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use Illuminate\Support\Facades\Cache;

class ChartServiceLegacy
{
    use ChartQueriesLegacy;

    public Company $company;

    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    /**
     * Returns an array of currencies that have
     * transacted with a company
     */
    public function getCurrencyCodes(): array
    {
        /* Get all the distinct client currencies */
        $currencies = Client::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->distinct()
            ->pluck('settings->currency_id as id');

        /* Push the company currency on also */
        $currencies->push((int) $this->company->settings->currency_id);

        /* Add our expense currencies*/
        $expense_currencies = Expense::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->distinct()
            ->pluck('currency_id as id');

        /* Merge and filter by unique */
        $currencies = $currencies->merge($expense_currencies)->unique();


        /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
        $cache_currencies = app('currencies');

        $filtered_currencies = $cache_currencies->whereIn('id', $currencies)->all();

        $final_currencies = [];

        foreach ($filtered_currencies as $c_currency) {
            $final_currencies[$c_currency['id']] = $c_currency['code'];
        }

        return $final_currencies;
    }

    /* Chart Data */
    public function chart_summary($start_date, $end_date): array
    {
        $currencies = $this->getCurrencyCodes();

        $data = [];

        foreach ($currencies as $key => $value) {
            $data[$key]['invoices'] = $this->getInvoiceChartQuery($start_date, $end_date, $key);
            $data[$key]['payments'] = $this->getPaymentChartQuery($start_date, $end_date, $key);
            $data[$key]['expenses'] = $this->getExpenseChartQuery($start_date, $end_date, $key);
        }

        return $data;
    }

    /* Chart Data */

    /* Totals */

    public function totals($start_date, $end_date): array
    {
        $data = [];

        $data['currencies'] = $this->getCurrencyCodes();

        foreach ($data['currencies'] as $key => $value) {
            $revenue = $this->getRevenue($start_date, $end_date);
            $outstanding = $this->getOutstanding($start_date, $end_date);
            $expenses = $this->getExpenses($start_date, $end_date);

            $data[$key]['revenue'] = count($revenue) > 0 ? $revenue[array_search($key, array_column($revenue, 'currency_id'))] : new \stdClass();
            $data[$key]['outstanding'] = count($outstanding) > 0 ? $outstanding[array_search($key, array_column($outstanding, 'currency_id'))] : new \stdClass();
            $data[$key]['expenses'] = count($expenses) > 0 ? $expenses[array_search($key, array_column($expenses, 'currency_id'))] : new \stdClass();
        }

        return $data;
    }

    public function getRevenue($start_date, $end_date): array
    {
        $revenue = $this->getRevenueQuery($start_date, $end_date);
        $revenue = $this->addCurrencyCodes($revenue);

        return $revenue;
    }

    public function getOutstanding($start_date, $end_date): array
    {
        $outstanding = $this->getOutstandingQuery($start_date, $end_date);
        $outstanding = $this->addCurrencyCodes($outstanding);

        return $outstanding;
    }

    public function getExpenses($start_date, $end_date): array
    {
        $expenses = $this->getExpenseQuery($start_date, $end_date);
        $expenses = $this->addCurrencyCodes($expenses);

        return $expenses;
    }

    /* Totals */

    /* Helpers */

    private function addCurrencyCodes($data_set): array
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
        $currencies = app('currencies');

        foreach ($data_set as $key => $value) {
            $data_set[$key]->currency_id = str_replace('"', '', $value->currency_id);
            $data_set[$key]->code = $this->getCode($currencies, $data_set[$key]->currency_id);
        }

        return $data_set;
    }

    private function getCode($currencies, $currency_id): string
    {
        $currency_id = str_replace('"', '', $currency_id);

        $currency = $currencies->filter(function ($item) use ($currency_id) {
            return $item->id == $currency_id;
        })->first();

        if ($currency) {
            return $currency->code;
        }

        return '';
    }
}
