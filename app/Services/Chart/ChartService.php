<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Chart;

use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use App\Services\Chart\CashFlowForecastService;
use App\Services\Chart\ClientPaymentAnalyticsService;

class ChartService
{
    use ChartQueries;
    use ChartCalculations;
    use AnalyticsQueries;

    public function __construct(public Company $company, private User $user, private bool $is_admin, private bool $include_drafts = false) {}

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
            ->when(!$this->is_admin, function ($query) {
                $query->where('user_id', $this->user->id);
            })
            ->distinct()
            ->pluck('settings->currency_id as id');

        /* Push the company currency on also */
        $currencies->push((int) $this->company->settings->currency_id);

        /* Add our expense currencies*/
        $expense_currencies = Expense::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->when(!$this->is_admin, function ($query) {
                $query->where('user_id', $this->user->id);
            })
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
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;

        foreach ($currencies as $key => $value) {
            $data[$key]['invoices'] = $this->getInvoiceChartQuery($start_date, $end_date, $key);
            $data[$key]['outstanding'] = $this->getOutstandingChartQuery($start_date, $end_date, $key);
            $data[$key]['payments'] = $this->getPaymentChartQuery($start_date, $end_date, $key);
            $data[$key]['expenses'] = $this->getExpenseChartQuery($start_date, $end_date, $key);
        }

        $data[999]['invoices'] = $this->getAggregateInvoiceChartQuery($start_date, $end_date);
        $data[999]['outstanding'] = $this->getAggregateOutstandingChartQuery($start_date, $end_date);
        $data[999]['payments'] = $this->getAggregatePaymentChartQuery($start_date, $end_date);
        $data[999]['expenses'] = $this->getAggregateExpenseChartQuery($start_date, $end_date);

        return $data;
    }

    /* Chart Data */

    /* Totals */

    public function totals($start_date, $end_date): array
    {
        $data = [];

        $data['currencies'] = $this->getCurrencyCodes();

        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;

        $revenue = $this->getRevenue($start_date, $end_date);
        $outstanding = $this->getOutstanding($start_date, $end_date);
        $expenses = $this->getExpenses($start_date, $end_date);
        $invoices = $this->getInvoices($start_date, $end_date);

        foreach ($data['currencies'] as $key => $value) {

            $invoices_set = array_search($key, array_column($invoices, 'currency_id'));
            $revenue_set = array_search($key, array_column($revenue, 'currency_id'));
            $outstanding_set = array_search($key, array_column($outstanding, 'currency_id'));
            $expenses_set = array_search($key, array_column($expenses, 'currency_id'));

            $data[$key]['invoices'] = $invoices_set !== false ? $invoices[array_search($key, array_column($invoices, 'currency_id'))] : new \stdClass();
            $data[$key]['revenue'] = $revenue_set !== false ? $revenue[array_search($key, array_column($revenue, 'currency_id'))] : new \stdClass();
            $data[$key]['outstanding'] = $outstanding_set !== false ? $outstanding[array_search($key, array_column($outstanding, 'currency_id'))] : new \stdClass();
            $data[$key]['expenses'] = $expenses_set !== false ? $expenses[array_search($key, array_column($expenses, 'currency_id'))] : new \stdClass();

        }

        $aggregate_revenue = $this->getAggregateRevenueQuery($start_date, $end_date);
        $aggregate_outstanding = $this->getAggregateOutstandingQuery($start_date, $end_date);
        $aggregate_expenses = $this->getAggregateExpenseQuery($start_date, $end_date);
        $aggregate_invoices = $this->getAggregateInvoicesQuery($start_date, $end_date);

        $data[999]['invoices'] = $aggregate_invoices !== false ? reset($aggregate_invoices) : new \stdClass();
        $data[999]['expenses'] = $aggregate_expenses !== false ? reset($aggregate_expenses) : new \stdClass();
        $data[999]['outstanding'] = $aggregate_outstanding !== false ? reset($aggregate_outstanding) : new \stdClass();
        $data[999]['revenue'] = $aggregate_revenue !== false ? reset($aggregate_revenue) : new \stdClass();


        return $data;
    }

    public function getInvoices($start_date, $end_date): array
    {
        $revenue = $this->getInvoicesQuery($start_date, $end_date);
        $revenue = $this->addCurrencyCodes($revenue);

        return $revenue;
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

    /* Analytics */

    /**
     * Analytics chart summary — time-series data for analytics charts.
     * Returns per-currency + aggregate (key 999) data matching chart_summary() format.
     */
    public function analytics_summary($start_date, $end_date): array
    {
        $currencies = $this->getCurrencyCodes();

        $data = [];
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;

        foreach ($currencies as $key => $value) {
            $data[$key]['mrr'] = $this->getMrrChartQuery($start_date, $end_date, $key);
            $data[$key]['payment_delay'] = $this->getPaymentDelayChartQuery($start_date, $end_date, $key);
            $data[$key]['quote_pipeline'] = $this->getQuotePipelineChartQuery($start_date, $end_date, $key);
            $data[$key]['late_payment_rate'] = $this->getLatePaymentRateChartQuery($start_date, $end_date, $key);
        }

        $data[999]['mrr'] = $this->getAggregateMrrChartQuery($start_date, $end_date);
        $data[999]['payment_delay'] = $this->getAggregatePaymentDelayChartQuery($start_date, $end_date);
        $data[999]['quote_pipeline'] = $this->getAggregateQuotePipelineChartQuery($start_date, $end_date);
        $data[999]['late_payment_rate'] = $this->getAggregateLatePaymentRateChartQuery($start_date, $end_date);

        return $data;
    }

    /**
     * Analytics totals — snapshot KPIs for analytics dashboard cards.
     * Returns per-currency + aggregate (key 999) data matching totals() format.
     */
    public function analytics_totals($start_date, $end_date): array
    {
        $data = [];

        $data['currencies'] = $this->getCurrencyCodes();
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;

        $mrr_totals = $this->getMrrTotalQuery();
        $aging_totals = $this->getAgingBucketTotals();
        $recurring_expense_totals = $this->getRecurringExpenseTotalQuery();

        foreach ($data['currencies'] as $key => $value) {
            $mrr_set = array_search($key, array_column($mrr_totals, 'currency_id'));
            $aging_set = array_search($key, array_column($aging_totals, 'currency_id'));
            $re_set = array_search($key, array_column($recurring_expense_totals, 'currency_id'));

            $data[$key]['mrr'] = $mrr_set !== false ? $mrr_totals[$mrr_set] : new \stdClass();
            $data[$key]['aging'] = $aging_set !== false ? $aging_totals[$aging_set] : new \stdClass();
            $data[$key]['recurring_expenses'] = $re_set !== false ? $recurring_expense_totals[$re_set] : new \stdClass();
        }

        $aggregate_mrr = $this->getAggregateMrrTotalQuery();
        $aggregate_aging = $this->getAggregateAgingBucketTotals();
        $aggregate_recurring_expenses = $this->getAggregateRecurringExpenseTotalQuery();
        $company_payment = $this->getCompanyPaymentSummary();

        $data[999]['mrr'] = ! empty($aggregate_mrr) ? reset($aggregate_mrr) : new \stdClass();
        $data[999]['aging'] = ! empty($aggregate_aging) ? reset($aggregate_aging) : new \stdClass();
        $data[999]['recurring_expenses'] = ! empty($aggregate_recurring_expenses) ? reset($aggregate_recurring_expenses) : new \stdClass();
        $data[999]['payment_analytics'] = ! empty($company_payment) ? reset($company_payment) : new \stdClass();

        return $data;
    }

    /**
     * Cash flow forecast — time-bucketed inflow/outflow projection.
     */
    public function cashflow_forecast(string $start_date, string $end_date, string $bucket_type = 'monthly'): array
    {
        $forecast = new CashFlowForecastService($this->company, $start_date, $end_date, $bucket_type);

        return $forecast->generate(
            $this->getOutstandingInvoicesForForecasting(),
            $this->getRecurringInvoiceProjections(),
            $this->getRecurringExpenseProjections(),
            $this->getUpcomingExpenses($start_date, $end_date),
            $this->getOpenQuotesForForecasting(),
            $this->getCompanyPaymentSummary()
        );
    }

    /**
     * Client payment analytics — scorecards with risk scoring.
     */
    public function client_payment_analytics(): array
    {
        $analytics = new ClientPaymentAnalyticsService($this->company);

        return $analytics->generate(
            $this->getClientPaymentSummary(null),
            $this->getCompanyPaymentSummary()
        );
    }

    /**
     * Project analytics — budget utilization and profitability.
     */
    public function project_analytics(): array
    {
        return [
            'budget_summary' => $this->getProjectBudgetSummary(),
            'profitability' => $this->getProjectProfitability(),
        ];
    }

    /* Analytics */

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * calculatedField
     *
     * @param  array $data -
     *
     * field - list of fields for calculation
     * period - current/previous
     * calculation - sum/count/avg
     *
     * May require currency_id
     *
     * date_range - this_month
     * or
     * start_date - end_date
     */
    public function getCalculatedField(array $data)
    {
        $results = 0;

        match ($data['field']) {
            'active_invoices' => $results = $this->getActiveInvoices($data),
            'outstanding_invoices' => $results = $this->getOutstandingInvoices($data),
            'completed_payments' => $results = $this->getCompletedPayments($data),
            'refunded_payments' => $results = $this->getRefundedPayments($data),
            'active_quotes' => $results = $this->getActiveQuotes($data),
            'unapproved_quotes' => $results = $this->getUnapprovedQuotes($data),
            'logged_tasks' => $results = $this->getLoggedTasks($data),
            'invoiced_tasks' => $results = $this->getInvoicedTasks($data),
            'paid_tasks' => $results = $this->getPaidTasks($data),
            'logged_expenses' => $results = $this->getLoggedExpenses($data),
            'pending_expenses' => $results = $this->getPendingExpenses($data),
            'invoiced_expenses' => $results = $this->getInvoicedExpenses($data),
            'invoice_paid_expenses' => $results = $this->getInvoicedPaidExpenses($data),
            default => $results = 0,
        };

        return $results;
    }

}
