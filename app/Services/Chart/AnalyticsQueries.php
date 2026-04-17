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

use Illuminate\Support\Facades\DB;

/**
 * Trait AnalyticsQueries.
 *
 * Provides raw SQL queries for predictive analytics and forecasting.
 * Expects $this->company, $this->user, and $this->is_admin from the consuming class.
 */
trait AnalyticsQueries
{
    /**
     * Client Payment Delays
     *
     * Returns per-client payment delay statistics for completed payments.
     * Joins invoices → paymentables → payments to compute days between
     * invoice date and first payment date.
     *
     * @param int|null $client_id Optional single client filter
     * @return array<int, \stdClass> Each row: client_id, invoice_id, invoice_date, due_date, payment_date, payment_days, amount, currency_id
     */
    public function getClientPaymentDelays(?int $client_id = null): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;
        $client_filter = $client_id ? 'AND invoices.client_id = ' . $client_id : '';

        return DB::select("
            SELECT
                invoices.client_id,
                invoices.id as invoice_id,
                invoices.date as invoice_date,
                invoices.due_date,
                MIN(payments.date) as payment_date,
                DATEDIFF(MIN(payments.date), invoices.date) as payment_days,
                invoices.amount,
                IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) AS currency_id
            FROM invoices
            JOIN paymentables
                ON paymentables.paymentable_id = invoices.id
                AND paymentables.paymentable_type = 'invoices'
                AND paymentables.deleted_at IS NULL
            JOIN payments
                ON payments.id = paymentables.payment_id
                AND payments.status_id = 4
                AND payments.is_deleted = 0
                AND payments.company_id = :company_id_pay
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.status_id = 4
            {$user_filter}
            {$client_filter}
            GROUP BY invoices.client_id, invoices.id, invoices.date, invoices.due_date, invoices.amount, currency_id
            HAVING DATEDIFF(MIN(payments.date), invoices.date) >= 0
            ORDER BY invoices.client_id, invoices.date
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'company_id_pay' => $this->company->id,
        ]);
    }

    /**
     * Client Payment Summary
     *
     * Returns aggregated payment statistics per client:
     * average payment days, standard deviation, late payment ratio, and invoice count.
     *
     * @param int|null $client_id Optional single client filter
     * @return array<int, \stdClass> Each row: client_id, avg_payment_days, stddev_payment_days, total_invoices, late_invoices, late_payment_ratio, currency_id
     */
    public function getClientPaymentSummary(?int $client_id = null): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;
        $client_filter = $client_id ? 'AND invoices.client_id = ' . $client_id : '';

        return DB::select("
            SELECT
                invoices.client_id,
                ROUND(AVG(DATEDIFF(MIN_pay.first_payment_date, invoices.date)), 2) as avg_payment_days,
                ROUND(STDDEV(DATEDIFF(MIN_pay.first_payment_date, invoices.date)), 2) as stddev_payment_days,
                COUNT(*) as total_invoices,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND MIN_pay.first_payment_date > invoices.due_date THEN 1 ELSE 0 END) as late_invoices,
                ROUND(
                    SUM(CASE WHEN invoices.due_date IS NOT NULL AND MIN_pay.first_payment_date > invoices.due_date THEN 1 ELSE 0 END)
                    / NULLIF(SUM(CASE WHEN invoices.due_date IS NOT NULL THEN 1 ELSE 0 END), 0), 4
                ) as late_payment_ratio,
                IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) AS currency_id
            FROM invoices
            JOIN ({$this->minPaymentSubquerySql()}) as MIN_pay
                ON MIN_pay.invoice_id = invoices.id
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.status_id = 4
            AND MIN_pay.first_payment_date >= invoices.date
            {$user_filter}
            {$client_filter}
            GROUP BY invoices.client_id, currency_id
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'company_id_pay' => $this->company->id,
        ]);
    }

    /**
     * Company-Wide Payment Summary
     *
     * Returns a single row of aggregate payment statistics across all clients.
     * Used as a fallback when a client has insufficient payment history.
     *
     * @return array<int, \stdClass> Single row: avg_payment_days, stddev_payment_days, total_invoices, late_invoices, late_payment_ratio
     */
    public function getCompanyPaymentSummary(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                ROUND(AVG(DATEDIFF(MIN_pay.first_payment_date, invoices.date)), 2) as avg_payment_days,
                ROUND(STDDEV(DATEDIFF(MIN_pay.first_payment_date, invoices.date)), 2) as stddev_payment_days,
                COUNT(*) as total_invoices,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND MIN_pay.first_payment_date > invoices.due_date THEN 1 ELSE 0 END) as late_invoices,
                ROUND(
                    SUM(CASE WHEN invoices.due_date IS NOT NULL AND MIN_pay.first_payment_date > invoices.due_date THEN 1 ELSE 0 END)
                    / NULLIF(SUM(CASE WHEN invoices.due_date IS NOT NULL THEN 1 ELSE 0 END), 0), 4
                ) as late_payment_ratio
            FROM invoices
            JOIN ({$this->minPaymentSubquerySql()}) as MIN_pay
                ON MIN_pay.invoice_id = invoices.id
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.status_id = 4
            AND MIN_pay.first_payment_date >= invoices.date
            {$user_filter}
        ", [
            'company_id' => $this->company->id,
            'company_id_pay' => $this->company->id,
        ]);
    }

    /**
     * Outstanding Invoices With Client Analytics
     *
     * Returns all unpaid invoices (sent/partial) with their client's
     * payment behavior for cash flow forecasting.
     *
     * @return array<int, \stdClass> Each row: invoice_id, client_id, amount, balance, date, due_date, currency_id, client_avg_payment_days, client_late_ratio
     */
    public function getOutstandingInvoicesForForecasting(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;
        $user_filter_inv = $this->is_admin ? '' : 'AND inv.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                invoices.id as invoice_id,
                invoices.client_id,
                invoices.amount,
                invoices.balance,
                invoices.date as invoice_date,
                invoices.due_date,
                IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) AS currency_id,
                invoices.exchange_rate,
                client_stats.avg_payment_days as client_avg_payment_days,
                client_stats.late_payment_ratio as client_late_ratio,
                client_stats.total_invoices as client_data_points
            FROM invoices
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            LEFT JOIN (
                SELECT
                    inv.client_id,
                    ROUND(AVG(DATEDIFF(MIN_pay.first_payment_date, inv.date)), 2) as avg_payment_days,
                    ROUND(
                        SUM(CASE WHEN inv.due_date IS NOT NULL AND MIN_pay.first_payment_date > inv.due_date THEN 1 ELSE 0 END)
                        / NULLIF(SUM(CASE WHEN inv.due_date IS NOT NULL THEN 1 ELSE 0 END), 0), 4
                    ) as late_payment_ratio,
                    COUNT(*) as total_invoices
                FROM invoices inv
                JOIN ({$this->minPaymentSubquerySql('company_id_pay_inner')}) as MIN_pay
                    ON MIN_pay.invoice_id = inv.id
                WHERE inv.is_deleted = 0
                AND inv.status_id = 4
                AND inv.company_id = :company_id_stats
                AND MIN_pay.first_payment_date >= inv.date
                {$user_filter_inv}
                GROUP BY inv.client_id
            ) as client_stats
                ON client_stats.client_id = invoices.client_id
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.status_id IN (2, 3)
            {$user_filter}
            ORDER BY invoices.due_date ASC
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'company_id_stats' => $this->company->id,
            'company_id_pay_inner' => $this->company->id,
        ]);
    }

    /**
     * Recurring Invoice Projections
     *
     * Returns active recurring invoices for forward projection of expected inflows.
     *
     * @return array<int, \stdClass> Each row: id, client_id, amount, frequency_id, next_send_date, remaining_cycles, auto_bill_enabled, currency_id
     */
    public function getRecurringInvoiceProjections(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND recurring_invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                recurring_invoices.id,
                recurring_invoices.client_id,
                recurring_invoices.amount,
                recurring_invoices.frequency_id,
                recurring_invoices.date,
                recurring_invoices.next_send_date,
                recurring_invoices.remaining_cycles,
                recurring_invoices.auto_bill_enabled,
                IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) AS currency_id,
                recurring_invoices.exchange_rate
            FROM recurring_invoices
            JOIN clients
                ON clients.id = recurring_invoices.client_id
                AND clients.is_deleted = 0
            WHERE recurring_invoices.company_id = :company_id
            AND recurring_invoices.is_deleted = 0
            AND recurring_invoices.status_id = 2
            {$user_filter}
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Recurring Expense Projections
     *
     * Returns active recurring expenses for forward projection of expected outflows.
     *
     * @return array<int, \stdClass> Each row: id, amount, frequency_id, next_send_date, remaining_cycles, currency_id
     */
    public function getRecurringExpenseProjections(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND recurring_expenses.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                recurring_expenses.id,
                recurring_expenses.amount,
                recurring_expenses.frequency_id,
                recurring_expenses.next_send_date,
                recurring_expenses.remaining_cycles,
                IFNULL(recurring_expenses.currency_id, :company_currency) as currency_id,
                recurring_expenses.exchange_rate,
                recurring_expenses.tax_rate1,
                recurring_expenses.tax_rate2,
                recurring_expenses.tax_rate3,
                recurring_expenses.tax_amount1,
                recurring_expenses.tax_amount2,
                recurring_expenses.tax_amount3,
                recurring_expenses.uses_inclusive_taxes
            FROM recurring_expenses
            WHERE recurring_expenses.company_id = :company_id
            AND recurring_expenses.is_deleted = 0
            AND recurring_expenses.status_id = 2
            AND recurring_expenses.next_send_date IS NOT NULL
            {$user_filter}
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Upcoming Expenses (Non-Recurring)
     *
     * Returns scheduled one-off expenses within a date range for cash flow forecasting.
     *
     * @param string $start_date
     * @param string $end_date
     * @return array<int, \stdClass> Each row: id, amount, date, currency_id
     */
    public function getUpcomingExpenses(string $start_date, string $end_date): array
    {
        $user_filter = $this->is_admin ? '' : 'AND expenses.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                expenses.id,
                CASE
                    WHEN expenses.uses_inclusive_taxes = 0 THEN
                        expenses.amount +
                        (COALESCE(expenses.tax_amount1, 0) + COALESCE(expenses.tax_amount2, 0) + COALESCE(expenses.tax_amount3, 0)) +
                        (
                            (expenses.amount * COALESCE(expenses.tax_rate1, 0)/100) +
                            (expenses.amount * COALESCE(expenses.tax_rate2, 0)/100) +
                            (expenses.amount * COALESCE(expenses.tax_rate3, 0)/100)
                        )
                    ELSE expenses.amount
                END as amount,
                expenses.date,
                IFNULL(expenses.currency_id, :company_currency) as currency_id,
                expenses.exchange_rate
            FROM expenses
            LEFT JOIN clients
                ON clients.id = expenses.client_id
            LEFT JOIN vendors
                ON vendors.id = expenses.vendor_id
            WHERE expenses.company_id = :company_id
            AND expenses.is_deleted = 0
            AND (expenses.date BETWEEN :start_date AND :end_date)
            {$user_filter}
            AND (clients.id IS NULL OR clients.is_deleted = 0)
            AND (vendors.id IS NULL OR vendors.is_deleted = 0)
            ORDER BY expenses.date ASC
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Quote Conversion History
     *
     * Returns quote-to-invoice conversion data for forecasting open quote revenue.
     * Groups by client to compute per-client conversion rates.
     *
     * @return array<int, \stdClass> Each row: client_id, total_quotes, converted_quotes, conversion_rate, avg_conversion_days, total_value, converted_value
     */
    public function getQuoteConversionHistory(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND quotes.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                quotes.client_id,
                COUNT(*) as total_quotes,
                SUM(CASE WHEN quotes.invoice_id IS NOT NULL THEN 1 ELSE 0 END) as converted_quotes,
                ROUND(
                    SUM(CASE WHEN quotes.invoice_id IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*), 4
                ) as conversion_rate,
                ROUND(
                    AVG(
                        CASE WHEN quotes.invoice_id IS NOT NULL
                            THEN DATEDIFF(invoices.date, quotes.date)
                            ELSE NULL
                        END
                    ), 2
                ) as avg_conversion_days,
                SUM(quotes.amount) as total_value,
                SUM(CASE WHEN quotes.invoice_id IS NOT NULL THEN quotes.amount ELSE 0 END) as converted_value,
                IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) AS currency_id
            FROM quotes
            JOIN clients
                ON clients.id = quotes.client_id
                AND clients.is_deleted = 0
            LEFT JOIN invoices
                ON invoices.id = quotes.invoice_id
                AND invoices.is_deleted = 0
            WHERE quotes.company_id = :company_id
            AND quotes.is_deleted = 0
            AND quotes.status_id IN (2, 3, 4, 5)
            {$user_filter}
            GROUP BY quotes.client_id, currency_id
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Open Quotes For Forecasting
     *
     * Returns active/approved quotes that haven't been converted yet,
     * along with their client's historical conversion rate.
     *
     * @return array<int, \stdClass> Each row: quote_id, client_id, amount, date, due_date, currency_id, client_conversion_rate, client_avg_conversion_days
     */
    public function getOpenQuotesForForecasting(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND quotes.user_id = ' . $this->user->id;
        $user_filter_q = $this->is_admin ? '' : 'AND q.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                quotes.id as quote_id,
                quotes.client_id,
                quotes.amount,
                quotes.date,
                quotes.due_date,
                IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) AS currency_id,
                quotes.exchange_rate,
                COALESCE(client_conv.conversion_rate, 0) as client_conversion_rate,
                COALESCE(client_conv.avg_conversion_days, 0) as client_avg_conversion_days
            FROM quotes
            JOIN clients
                ON clients.id = quotes.client_id
                AND clients.is_deleted = 0
            LEFT JOIN (
                SELECT
                    q.client_id,
                    ROUND(
                        SUM(CASE WHEN q.invoice_id IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*), 4
                    ) as conversion_rate,
                    ROUND(
                        AVG(
                            CASE WHEN q.invoice_id IS NOT NULL
                                THEN DATEDIFF(inv.date, q.date)
                                ELSE NULL
                            END
                        ), 2
                    ) as avg_conversion_days
                FROM quotes q
                LEFT JOIN invoices inv
                    ON inv.id = q.invoice_id
                    AND inv.is_deleted = 0
                WHERE q.is_deleted = 0
                AND q.status_id IN (2, 3, 4, 5)
                AND q.company_id = :company_id_conv
                {$user_filter_q}
                GROUP BY q.client_id
            ) as client_conv
                ON client_conv.client_id = quotes.client_id
            WHERE quotes.company_id = :company_id
            AND quotes.is_deleted = 0
            AND quotes.status_id IN (2, 3)
            AND quotes.invoice_id IS NULL
            {$user_filter}
            ORDER BY quotes.date ASC
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'company_id_conv' => $this->company->id,
        ]);
    }

    /**
     * Invoice Payment Timeline
     *
     * Returns a time-series of invoice-to-payment delays for trend analysis.
     * Groups by month to show how payment behavior changes over time.
     *
     * @param string $start_date
     * @param string $end_date
     * @return array<int, \stdClass> Each row: month, avg_payment_days, invoice_count, late_count, on_time_count
     */
    public function getPaymentDelayTrend(string $start_date, string $end_date): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                DATE_FORMAT(invoices.date, '%Y-%m') as month,
                ROUND(AVG(DATEDIFF(MIN_pay.first_payment_date, invoices.date)), 2) as avg_payment_days,
                COUNT(*) as invoice_count,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND MIN_pay.first_payment_date > invoices.due_date THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND MIN_pay.first_payment_date <= invoices.due_date THEN 1 ELSE 0 END) as on_time_count
            FROM invoices
            JOIN ({$this->minPaymentSubquerySql()}) as MIN_pay
                ON MIN_pay.invoice_id = invoices.id
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.status_id = 4
            AND MIN_pay.first_payment_date >= invoices.date
            AND (invoices.date BETWEEN :start_date AND :end_date)
            {$user_filter}
            GROUP BY month
            ORDER BY month ASC
        ", [
            'company_id' => $this->company->id,
            'company_id_pay' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    // ─── Chartable Time-Series Queries ──────────────────────────────

    private const FREQUENCY_MONTHLY_DIVISOR = [
        1  => 0.032854209445585, // Daily:  1/30.44
        2  => 0.23094688221709,  // Weekly: 1/4.33
        3  => 0.46082949308756,  // Two Weeks: 1/2.17
        4  => 0.91996319779209,  // Four Weeks: 1/1.087
        5  => 1,                 // Monthly
        6  => 2,                 // Two Months
        7  => 3,                 // Three Months
        8  => 4,                 // Four Months
        9  => 6,                 // Six Months
        10 => 12,                // Annually
        11 => 24,                // Two Years
        12 => 36,                // Three Years
    ];

    private const FREQUENCY_INTERVALS = [
        1  => ['addDay', 1],
        2  => ['addWeek', 1],
        3  => ['addWeeks', 2],
        4  => ['addWeeks', 4],
        5  => ['addMonthNoOverflow', 1],
        6  => ['addMonthsNoOverflow', 2],
        7  => ['addMonthsNoOverflow', 3],
        8  => ['addMonthsNoOverflow', 4],
        9  => ['addMonthsNoOverflow', 6],
        10 => ['addYear', 1],
        11 => ['addYears', 2],
        12 => ['addYears', 3],
    ];

    /**
     * SQL for the first-payment-date-per-invoice subquery, scoped by company.
     */
    private function minPaymentSubquerySql(string $companyParam = 'company_id_pay'): string
    {
        return "
            SELECT
                paymentables.paymentable_id as invoice_id,
                MIN(payments.date) as first_payment_date
            FROM paymentables
            JOIN payments
                ON payments.id = paymentables.payment_id
                AND payments.status_id = 4
                AND payments.is_deleted = 0
                AND payments.company_id = :{$companyParam}
            WHERE paymentables.paymentable_type = 'invoices'
            AND paymentables.deleted_at IS NULL
            GROUP BY paymentables.paymentable_id
        ";
    }

    /**
     * Advance a Carbon date by a recurring frequency.
     */
    private function advanceByFrequency(\Carbon\Carbon $date, int $frequencyId): ?\Carbon\Carbon
    {
        if (! isset(self::FREQUENCY_INTERVALS[$frequencyId])) {
            return null;
        }

        [$method, $value] = self::FREQUENCY_INTERVALS[$frequencyId];

        return $date->copy()->{$method}($value);
    }

    /**
     * MRR Chart (per currency)
     *
     * Projects active recurring invoices forward by their frequency to show
     * expected monthly recurring revenue across the date range.
     * Returns {total, date} pairs for line chart rendering.
     *
     * @param string $start_date
     * @param string $end_date
     * @param int $currency_id
     * @return array<int, \stdClass>
     */
    public function getMrrChartQuery(string $start_date, string $end_date, int $currency_id): array
    {
        $recurring = $this->getRecurringInvoiceProjections();
        $start = \Carbon\Carbon::parse($start_date);
        $end = \Carbon\Carbon::parse($end_date);

        $buckets = [];

        foreach ($recurring as $ri) {
            if ((int) $ri->currency_id !== $currency_id) {
                continue;
            }

            $this->projectRecurringIntoMonthlyBuckets($ri, (float) $ri->amount, $start, $end, $buckets);
        }

        ksort($buckets);

        return array_map(fn ($date, $total) => (object) ['total' => round($total, 2), 'date' => $date], array_keys($buckets), array_values($buckets));
    }

    /**
     * MRR Chart (aggregate across currencies)
     *
     * Projects active recurring invoices forward, converting all currencies
     * to company currency via exchange_rate.
     *
     * @param string $start_date
     * @param string $end_date
     * @return array<int, \stdClass>
     */
    public function getAggregateMrrChartQuery(string $start_date, string $end_date): array
    {
        $recurring = $this->getRecurringInvoiceProjections();
        $start = \Carbon\Carbon::parse($start_date);
        $end = \Carbon\Carbon::parse($end_date);

        $buckets = [];

        foreach ($recurring as $ri) {
            $rate = (float) ($ri->exchange_rate ?? 1);
            $amount = (float) $ri->amount / ($rate == 0 ? 1 : $rate);

            $this->projectRecurringIntoMonthlyBuckets($ri, $amount, $start, $end, $buckets);
        }

        ksort($buckets);

        return array_map(fn ($date, $total) => (object) ['total' => round($total, 2), 'date' => $date], array_keys($buckets), array_values($buckets));
    }

    /**
     * Normalize a recurring invoice's amount to its monthly equivalent and
     * spread it across every month bucket in the active subscription window.
     */
    private function projectRecurringIntoMonthlyBuckets(\stdClass $ri, float $amount, \Carbon\Carbon $start, \Carbon\Carbon $end, array &$buckets): void
    {
        $frequencyId = (int) $ri->frequency_id;
        $remainingCycles = (int) $ri->remaining_cycles;
        $nextSendDate = $ri->next_send_date ? \Carbon\Carbon::parse($ri->next_send_date) : null;

        $divisor = self::FREQUENCY_MONTHLY_DIVISOR[$frequencyId] ?? 1;
        $monthlyMrr = $amount / $divisor;

        // Determine when the subscription ends
        if ($remainingCycles === -1 || $nextSendDate === null) {
            $subEnd = $end->copy();
        } else {
            $subEnd = $nextSendDate->copy();
            for ($i = 0; $i < $remainingCycles; $i++) {
                $next = $this->advanceByFrequency($subEnd, $frequencyId);
                if ($next === null) {
                    break;
                }
                $subEnd = $next;
            }
        }

        // Subscription ended before chart range
        if ($subEnd->lt($start)) {
            return;
        }

        // Subscription contributes MRR from its start date (the `date` field),
        // falling back to next_send_date, then chart start.
        $subscriptionStart = $ri->date ? \Carbon\Carbon::parse($ri->date) : ($nextSendDate ?? $start->copy());
        $activeStart = $subscriptionStart->gt($start) ? $subscriptionStart->copy()->startOfMonth() : $start->copy()->startOfMonth();
        $activeEnd = $subEnd->lt($end) ? $subEnd->copy()->startOfMonth() : $end->copy()->startOfMonth();

        $cursor = $activeStart->copy();

        while ($cursor->lte($activeEnd)) {
            $key = $cursor->format('Y-m-01');
            $buckets[$key] = ($buckets[$key] ?? 0) + $monthlyMrr;
            $cursor->addMonthNoOverflow();
        }
    }

    /**
     * Current MRR/ARR Total (per currency)
     *
     * Snapshot of current MRR based on active recurring invoices,
     * with frequency normalization to monthly equivalent.
     *
     * @return array<int, \stdClass> Each row: mrr, arr, currency_id
     */
    public function getMrrTotalQuery(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND recurring_invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                ROUND(SUM(
                    CASE recurring_invoices.frequency_id
                        WHEN 1 THEN recurring_invoices.amount * 30.44
                        WHEN 2 THEN recurring_invoices.amount * 4.33
                        WHEN 3 THEN recurring_invoices.amount * 2.17
                        WHEN 4 THEN recurring_invoices.amount * 1.087
                        WHEN 5 THEN recurring_invoices.amount
                        WHEN 6 THEN recurring_invoices.amount / 2
                        WHEN 7 THEN recurring_invoices.amount / 3
                        WHEN 8 THEN recurring_invoices.amount / 4
                        WHEN 9 THEN recurring_invoices.amount / 6
                        WHEN 10 THEN recurring_invoices.amount / 12
                        WHEN 11 THEN recurring_invoices.amount / 24
                        WHEN 12 THEN recurring_invoices.amount / 36
                        ELSE recurring_invoices.amount
                    END
                ), 2) as mrr,
                ROUND(SUM(
                    CASE recurring_invoices.frequency_id
                        WHEN 1 THEN recurring_invoices.amount * 30.44 * 12
                        WHEN 2 THEN recurring_invoices.amount * 4.33 * 12
                        WHEN 3 THEN recurring_invoices.amount * 2.17 * 12
                        WHEN 4 THEN recurring_invoices.amount * 1.087 * 12
                        WHEN 5 THEN recurring_invoices.amount * 12
                        WHEN 6 THEN recurring_invoices.amount * 6
                        WHEN 7 THEN recurring_invoices.amount * 4
                        WHEN 8 THEN recurring_invoices.amount * 3
                        WHEN 9 THEN recurring_invoices.amount * 2
                        WHEN 10 THEN recurring_invoices.amount
                        WHEN 11 THEN recurring_invoices.amount / 2
                        WHEN 12 THEN recurring_invoices.amount / 3
                        ELSE recurring_invoices.amount * 12
                    END
                ), 2) as arr,
                IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) AS currency_id
            FROM recurring_invoices
            JOIN clients
                ON clients.id = recurring_invoices.client_id
                AND clients.is_deleted = 0
            WHERE recurring_invoices.company_id = :company_id
            AND recurring_invoices.is_deleted = 0
            AND recurring_invoices.status_id = 2
            {$user_filter}
            GROUP BY currency_id
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Current MRR/ARR Total (aggregate)
     *
     * Single row with company-wide MRR/ARR converted to company currency.
     *
     * @return array<int, \stdClass> Single row: mrr, arr
     */
    public function getAggregateMrrTotalQuery(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND recurring_invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                ROUND(SUM(
                    CASE recurring_invoices.frequency_id
                        WHEN 1 THEN recurring_invoices.amount * 30.44
                        WHEN 2 THEN recurring_invoices.amount * 4.33
                        WHEN 3 THEN recurring_invoices.amount * 2.17
                        WHEN 4 THEN recurring_invoices.amount * 1.087
                        WHEN 5 THEN recurring_invoices.amount
                        WHEN 6 THEN recurring_invoices.amount / 2
                        WHEN 7 THEN recurring_invoices.amount / 3
                        WHEN 8 THEN recurring_invoices.amount / 4
                        WHEN 9 THEN recurring_invoices.amount / 6
                        WHEN 10 THEN recurring_invoices.amount / 12
                        WHEN 11 THEN recurring_invoices.amount / 24
                        WHEN 12 THEN recurring_invoices.amount / 36
                        ELSE recurring_invoices.amount
                    END
                    / COALESCE(NULLIF(recurring_invoices.exchange_rate, 0), 1)
                ), 2) as mrr,
                ROUND(SUM(
                    CASE recurring_invoices.frequency_id
                        WHEN 1 THEN recurring_invoices.amount * 30.44 * 12
                        WHEN 2 THEN recurring_invoices.amount * 4.33 * 12
                        WHEN 3 THEN recurring_invoices.amount * 2.17 * 12
                        WHEN 4 THEN recurring_invoices.amount * 1.087 * 12
                        WHEN 5 THEN recurring_invoices.amount * 12
                        WHEN 6 THEN recurring_invoices.amount * 6
                        WHEN 7 THEN recurring_invoices.amount * 4
                        WHEN 8 THEN recurring_invoices.amount * 3
                        WHEN 9 THEN recurring_invoices.amount * 2
                        WHEN 10 THEN recurring_invoices.amount
                        WHEN 11 THEN recurring_invoices.amount / 2
                        WHEN 12 THEN recurring_invoices.amount / 3
                        ELSE recurring_invoices.amount * 12
                    END
                    / COALESCE(NULLIF(recurring_invoices.exchange_rate, 0), 1)
                ), 2) as arr
            FROM recurring_invoices
            JOIN clients
                ON clients.id = recurring_invoices.client_id
                AND clients.is_deleted = 0
            WHERE recurring_invoices.company_id = :company_id
            AND recurring_invoices.is_deleted = 0
            AND recurring_invoices.status_id = 2
            {$user_filter}
        ", [
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Payment Delay Chart (per currency)
     *
     * Average payment delay per month for a specific currency.
     * Returns {total, date} pairs where total = avg days to payment.
     *
     * @param string $start_date
     * @param string $end_date
     * @param int $currency_id
     * @return array<int, \stdClass>
     */
    public function getPaymentDelayChartQuery(string $start_date, string $end_date, int $currency_id): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                ROUND(AVG(DATEDIFF(MIN_pay.first_payment_date, invoices.date)), 2) as total,
                DATE_FORMAT(invoices.date, '%Y-%m-01') as date
            FROM invoices
            JOIN ({$this->minPaymentSubquerySql()}) as MIN_pay
                ON MIN_pay.invoice_id = invoices.id
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.status_id = 4
            AND MIN_pay.first_payment_date >= invoices.date
            AND (invoices.date BETWEEN :start_date AND :end_date)
            AND IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) = :currency_id
            {$user_filter}
            GROUP BY DATE_FORMAT(invoices.date, '%Y-%m-01')
            ORDER BY DATE_FORMAT(invoices.date, '%Y-%m-01') ASC
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'company_id_pay' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Payment Delay Chart (aggregate)
     *
     * Average payment delay per month across all currencies.
     *
     * @param string $start_date
     * @param string $end_date
     * @return array<int, \stdClass>
     */
    public function getAggregatePaymentDelayChartQuery(string $start_date, string $end_date): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                ROUND(AVG(DATEDIFF(MIN_pay.first_payment_date, invoices.date)), 2) as total,
                DATE_FORMAT(invoices.date, '%Y-%m-01') as date
            FROM invoices
            JOIN ({$this->minPaymentSubquerySql()}) as MIN_pay
                ON MIN_pay.invoice_id = invoices.id
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.status_id = 4
            AND MIN_pay.first_payment_date >= invoices.date
            AND (invoices.date BETWEEN :start_date AND :end_date)
            {$user_filter}
            GROUP BY DATE_FORMAT(invoices.date, '%Y-%m-01')
            ORDER BY DATE_FORMAT(invoices.date, '%Y-%m-01') ASC
        ", [
            'company_id' => $this->company->id,
            'company_id_pay' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Quote Pipeline Chart (per currency)
     *
     * Total value of actionable quotes — sent/approved, not converted,
     * not expired — for a specific currency. Grouped by creation month.
     * Bounded by date range. A quote is expired when due_date < today.
     * Quotes with no due_date never expire.
     *
     * @param string $start_date
     * @param string $end_date
     * @param int $currency_id
     * @return array<int, \stdClass>
     */
    public function getQuotePipelineChartQuery(string $start_date, string $end_date, int $currency_id): array
    {
        $user_filter = $this->is_admin ? '' : 'AND quotes.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                SUM(quotes.amount) as total,
                DATE_FORMAT(quotes.date, '%Y-%m-01') as date
            FROM quotes
            JOIN clients
                ON clients.id = quotes.client_id
                AND clients.is_deleted = 0
            WHERE quotes.company_id = :company_id
            AND quotes.is_deleted = 0
            AND quotes.status_id IN (2, 3)
            AND quotes.invoice_id IS NULL
            AND (quotes.due_date IS NULL OR quotes.due_date >= CURDATE())
            AND (quotes.date BETWEEN :start_date AND :end_date)
            AND IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) = :currency_id
            {$user_filter}
            GROUP BY DATE_FORMAT(quotes.date, '%Y-%m-01')
            ORDER BY DATE_FORMAT(quotes.date, '%Y-%m-01') ASC
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Quote Pipeline Chart (aggregate)
     *
     * Total value of all actionable quotes — sent/approved, not converted,
     * not expired — across all currencies, converted to company currency.
     * Grouped by creation month. Bounded by date range.
     * A quote is expired when due_date < today. Quotes with no due_date never expire.
     *
     * @param string $start_date
     * @param string $end_date
     * @return array<int, \stdClass>
     */
    public function getAggregateQuotePipelineChartQuery(string $start_date, string $end_date): array
    {
        $user_filter = $this->is_admin ? '' : 'AND quotes.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                SUM(quotes.amount / COALESCE(NULLIF(quotes.exchange_rate, 0), 1)) as total,
                DATE_FORMAT(quotes.date, '%Y-%m-01') as date
            FROM quotes
            JOIN clients
                ON clients.id = quotes.client_id
                AND clients.is_deleted = 0
            WHERE quotes.company_id = :company_id
            AND quotes.is_deleted = 0
            AND quotes.status_id IN (2, 3)
            AND quotes.invoice_id IS NULL
            AND (quotes.due_date IS NULL OR quotes.due_date >= CURDATE())
            AND (quotes.date BETWEEN :start_date AND :end_date)
            {$user_filter}
            GROUP BY DATE_FORMAT(quotes.date, '%Y-%m-01')
            ORDER BY DATE_FORMAT(quotes.date, '%Y-%m-01') ASC
        ", [
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Late Payment Rate Chart (per currency)
     *
     * Percentage of invoices paid late per month.
     * Invoices without a due_date are excluded entirely — they cannot be late.
     * Returns {total, date} pairs where total = late ratio (0-1).
     *
     * @param string $start_date
     * @param string $end_date
     * @param int $currency_id
     * @return array<int, \stdClass>
     */
    public function getLatePaymentRateChartQuery(string $start_date, string $end_date, int $currency_id): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                ROUND(
                    SUM(CASE
                        WHEN invoices.status_id = 4 AND MIN_pay.first_payment_date > invoices.due_date THEN 1
                        WHEN invoices.status_id IN (2, 3) AND invoices.due_date < CURDATE() THEN 1
                        ELSE 0
                    END)
                    / NULLIF(COUNT(*), 0), 4
                ) as total,
                DATE_FORMAT(invoices.date, '%Y-%m-01') as date
            FROM invoices
            LEFT JOIN ({$this->minPaymentSubquerySql()}) as MIN_pay
                ON MIN_pay.invoice_id = invoices.id
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.due_date IS NOT NULL
            AND (
                invoices.status_id = 4
                OR (invoices.status_id IN (2, 3) AND invoices.due_date < CURDATE())
            )
            AND (invoices.date BETWEEN :start_date AND :end_date)
            AND IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) = :currency_id
            {$user_filter}
            GROUP BY DATE_FORMAT(invoices.date, '%Y-%m-01')
            ORDER BY DATE_FORMAT(invoices.date, '%Y-%m-01') ASC
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'company_id_pay' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Late Payment Rate Chart (aggregate)
     *
     * Percentage of invoices paid late per month across all currencies.
     * Invoices without a due_date are excluded entirely.
     *
     * @param string $start_date
     * @param string $end_date
     * @return array<int, \stdClass>
     */
    public function getAggregateLatePaymentRateChartQuery(string $start_date, string $end_date): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                ROUND(
                    SUM(CASE
                        WHEN invoices.status_id = 4 AND MIN_pay.first_payment_date > invoices.due_date THEN 1
                        WHEN invoices.status_id IN (2, 3) AND invoices.due_date < CURDATE() THEN 1
                        ELSE 0
                    END)
                    / NULLIF(COUNT(*), 0), 4
                ) as total,
                DATE_FORMAT(invoices.date, '%Y-%m-01') as date
            FROM invoices
            LEFT JOIN ({$this->minPaymentSubquerySql()}) as MIN_pay
                ON MIN_pay.invoice_id = invoices.id
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.due_date IS NOT NULL
            AND (
                invoices.status_id = 4
                OR (invoices.status_id IN (2, 3) AND invoices.due_date < CURDATE())
            )
            AND (invoices.date BETWEEN :start_date AND :end_date)
            {$user_filter}
            GROUP BY DATE_FORMAT(invoices.date, '%Y-%m-01')
            ORDER BY DATE_FORMAT(invoices.date, '%Y-%m-01') ASC
        ", [
            'company_id' => $this->company->id,
            'company_id_pay' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * AR Aging Bucket Totals (per currency)
     *
     * Current snapshot of outstanding invoice amounts by aging bucket.
     * For stacked bar chart rendering.
     *
     * @return array<int, \stdClass> Each row: current_amount, age_0_30, age_31_60, age_61_90, age_91_120, age_120_plus, currency_id
     */
    public function getAgingBucketTotals(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                SUM(CASE WHEN invoices.due_date IS NULL OR invoices.due_date >= CURDATE() THEN invoices.balance ELSE 0 END) as current_amount,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND invoices.due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN invoices.balance ELSE 0 END) as age_0_30,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND invoices.due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND DATE_SUB(CURDATE(), INTERVAL 31 DAY) THEN invoices.balance ELSE 0 END) as age_31_60,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND invoices.due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND DATE_SUB(CURDATE(), INTERVAL 61 DAY) THEN invoices.balance ELSE 0 END) as age_61_90,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND invoices.due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 120 DAY) AND DATE_SUB(CURDATE(), INTERVAL 91 DAY) THEN invoices.balance ELSE 0 END) as age_91_120,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND invoices.due_date < DATE_SUB(CURDATE(), INTERVAL 120 DAY) THEN invoices.balance ELSE 0 END) as age_120_plus,
                IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) AS currency_id
            FROM invoices
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.status_id IN (2, 3)
            AND invoices.balance > 0
            {$user_filter}
            GROUP BY currency_id
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * AR Aging Bucket Totals (aggregate)
     *
     * Company-wide aging snapshot with exchange rate conversion.
     *
     * @return array<int, \stdClass> Single row: current_amount, age_0_30, age_31_60, age_61_90, age_91_120, age_120_plus
     */
    public function getAggregateAgingBucketTotals(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                SUM(CASE WHEN invoices.due_date IS NULL OR invoices.due_date >= CURDATE() THEN invoices.balance / COALESCE(NULLIF(invoices.exchange_rate, 0), 1) ELSE 0 END) as current_amount,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND invoices.due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN invoices.balance / COALESCE(NULLIF(invoices.exchange_rate, 0), 1) ELSE 0 END) as age_0_30,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND invoices.due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND DATE_SUB(CURDATE(), INTERVAL 31 DAY) THEN invoices.balance / COALESCE(NULLIF(invoices.exchange_rate, 0), 1) ELSE 0 END) as age_31_60,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND invoices.due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND DATE_SUB(CURDATE(), INTERVAL 61 DAY) THEN invoices.balance / COALESCE(NULLIF(invoices.exchange_rate, 0), 1) ELSE 0 END) as age_61_90,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND invoices.due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 120 DAY) AND DATE_SUB(CURDATE(), INTERVAL 91 DAY) THEN invoices.balance / COALESCE(NULLIF(invoices.exchange_rate, 0), 1) ELSE 0 END) as age_91_120,
                SUM(CASE WHEN invoices.due_date IS NOT NULL AND invoices.due_date < DATE_SUB(CURDATE(), INTERVAL 120 DAY) THEN invoices.balance / COALESCE(NULLIF(invoices.exchange_rate, 0), 1) ELSE 0 END) as age_120_plus
            FROM invoices
            JOIN clients
                ON clients.id = invoices.client_id
                AND clients.is_deleted = 0
            WHERE invoices.company_id = :company_id
            AND invoices.is_deleted = 0
            AND invoices.status_id IN (2, 3)
            AND invoices.balance > 0
            {$user_filter}
        ", [
            'company_id' => $this->company->id,
        ]);
    }

    // ─── Recurring Expense Analytics ────────────────────────────────

    /**
     * Recurring Expense Totals (per currency)
     *
     * Snapshot of current monthly recurring expenses based on active recurring expenses,
     * with frequency normalization to monthly equivalent. Mirrors getMrrTotalQuery for outflows.
     *
     * @return array<int, \stdClass> Each row: monthly_total, annual_total, count, currency_id
     */
    public function getRecurringExpenseTotalQuery(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND recurring_expenses.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                ROUND(SUM(
                    CASE recurring_expenses.frequency_id
                        WHEN 1 THEN recurring_expenses.amount * 30.44
                        WHEN 2 THEN recurring_expenses.amount * 4.33
                        WHEN 3 THEN recurring_expenses.amount * 2.17
                        WHEN 4 THEN recurring_expenses.amount * 1.087
                        WHEN 5 THEN recurring_expenses.amount
                        WHEN 6 THEN recurring_expenses.amount / 2
                        WHEN 7 THEN recurring_expenses.amount / 3
                        WHEN 8 THEN recurring_expenses.amount / 4
                        WHEN 9 THEN recurring_expenses.amount / 6
                        WHEN 10 THEN recurring_expenses.amount / 12
                        WHEN 11 THEN recurring_expenses.amount / 24
                        WHEN 12 THEN recurring_expenses.amount / 36
                        ELSE recurring_expenses.amount
                    END
                ), 2) as monthly_total,
                ROUND(SUM(
                    CASE recurring_expenses.frequency_id
                        WHEN 1 THEN recurring_expenses.amount * 30.44 * 12
                        WHEN 2 THEN recurring_expenses.amount * 4.33 * 12
                        WHEN 3 THEN recurring_expenses.amount * 2.17 * 12
                        WHEN 4 THEN recurring_expenses.amount * 1.087 * 12
                        WHEN 5 THEN recurring_expenses.amount * 12
                        WHEN 6 THEN recurring_expenses.amount * 6
                        WHEN 7 THEN recurring_expenses.amount * 4
                        WHEN 8 THEN recurring_expenses.amount * 3
                        WHEN 9 THEN recurring_expenses.amount * 2
                        WHEN 10 THEN recurring_expenses.amount
                        WHEN 11 THEN recurring_expenses.amount / 2
                        WHEN 12 THEN recurring_expenses.amount / 3
                        ELSE recurring_expenses.amount * 12
                    END
                ), 2) as annual_total,
                COUNT(*) as count,
                IFNULL(recurring_expenses.currency_id, :company_currency) as currency_id
            FROM recurring_expenses
            WHERE recurring_expenses.company_id = :company_id
            AND recurring_expenses.is_deleted = 0
            AND recurring_expenses.status_id = 2
            {$user_filter}
            GROUP BY currency_id
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Recurring Expense Totals (aggregate)
     *
     * Company-wide monthly/annual recurring expense total in company currency.
     *
     * @return array<int, \stdClass> Single row: monthly_total, annual_total, count
     */
    public function getAggregateRecurringExpenseTotalQuery(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND recurring_expenses.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
                ROUND(SUM(
                    CASE recurring_expenses.frequency_id
                        WHEN 1 THEN recurring_expenses.amount * 30.44
                        WHEN 2 THEN recurring_expenses.amount * 4.33
                        WHEN 3 THEN recurring_expenses.amount * 2.17
                        WHEN 4 THEN recurring_expenses.amount * 1.087
                        WHEN 5 THEN recurring_expenses.amount
                        WHEN 6 THEN recurring_expenses.amount / 2
                        WHEN 7 THEN recurring_expenses.amount / 3
                        WHEN 8 THEN recurring_expenses.amount / 4
                        WHEN 9 THEN recurring_expenses.amount / 6
                        WHEN 10 THEN recurring_expenses.amount / 12
                        WHEN 11 THEN recurring_expenses.amount / 24
                        WHEN 12 THEN recurring_expenses.amount / 36
                        ELSE recurring_expenses.amount
                    END
                    / COALESCE(NULLIF(recurring_expenses.exchange_rate, 0), 1)
                ), 2) as monthly_total,
                ROUND(SUM(
                    CASE recurring_expenses.frequency_id
                        WHEN 1 THEN recurring_expenses.amount * 30.44 * 12
                        WHEN 2 THEN recurring_expenses.amount * 4.33 * 12
                        WHEN 3 THEN recurring_expenses.amount * 2.17 * 12
                        WHEN 4 THEN recurring_expenses.amount * 1.087 * 12
                        WHEN 5 THEN recurring_expenses.amount * 12
                        WHEN 6 THEN recurring_expenses.amount * 6
                        WHEN 7 THEN recurring_expenses.amount * 4
                        WHEN 8 THEN recurring_expenses.amount * 3
                        WHEN 9 THEN recurring_expenses.amount * 2
                        WHEN 10 THEN recurring_expenses.amount
                        WHEN 11 THEN recurring_expenses.amount / 2
                        WHEN 12 THEN recurring_expenses.amount / 3
                        ELSE recurring_expenses.amount * 12
                    END
                    / COALESCE(NULLIF(recurring_expenses.exchange_rate, 0), 1)
                ), 2) as annual_total,
                COUNT(*) as count
            FROM recurring_expenses
            WHERE recurring_expenses.company_id = :company_id
            AND recurring_expenses.is_deleted = 0
            AND recurring_expenses.status_id = 2
            {$user_filter}
        ", [
            'company_id' => $this->company->id,
        ]);
    }

    // ─── Project Analytics ──────────────────────────────────────────

    /**
     * Project Budget Summary
     *
     * Returns per-project budget utilization: budgeted hours, hours logged
     * (from projects.current_hours which is pre-computed from task time_log),
     * task counts by invoiced status, and budget utilization percentage.
     *
     * Note: Task hours are stored as JSON in tasks.time_log and computed via PHP.
     * projects.current_hours is the pre-computed total maintained by TaskRepository.
     *
     * @return array<int, \stdClass>
     */
    public function getProjectBudgetSummary(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND projects.user_id = ' . $this->user->id;
        $user_filter_tasks = $this->is_admin ? '' : 'AND tasks.user_id = ' . $this->user->id;

        $results = DB::select("
            SELECT
                projects.id as project_id,
                projects.name as project_name,
                projects.client_id,
                projects.budgeted_hours,
                projects.current_hours,
                projects.task_rate,
                projects.due_date,
                COALESCE(task_stats.total_tasks, 0) as total_tasks,
                COALESCE(task_stats.invoiced_tasks, 0) as invoiced_tasks,
                COALESCE(task_stats.uninvoiced_tasks, 0) as uninvoiced_tasks,
                COALESCE(task_stats.running_tasks, 0) as running_tasks,
                ROUND(
                    CASE WHEN projects.budgeted_hours > 0
                        THEN projects.current_hours / projects.budgeted_hours
                        ELSE 0
                    END, 4
                ) as utilization,
                ROUND(
                    CASE WHEN projects.budgeted_hours > 0
                        THEN GREATEST(projects.budgeted_hours - projects.current_hours, 0)
                        ELSE 0
                    END, 2
                ) as hours_remaining,
                IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) AS currency_id
            FROM projects
            JOIN clients
                ON clients.id = projects.client_id
                AND clients.is_deleted = 0
            LEFT JOIN (
                SELECT
                    tasks.project_id,
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN tasks.invoice_id IS NOT NULL THEN 1 ELSE 0 END) as invoiced_tasks,
                    SUM(CASE WHEN tasks.invoice_id IS NULL THEN 1 ELSE 0 END) as uninvoiced_tasks,
                    SUM(CASE WHEN tasks.is_running = 1 THEN 1 ELSE 0 END) as running_tasks
                FROM tasks
                WHERE tasks.is_deleted = 0
                {$user_filter_tasks}
                GROUP BY tasks.project_id
            ) as task_stats
                ON task_stats.project_id = projects.id
            WHERE projects.company_id = :company_id
            AND projects.is_deleted = 0
            {$user_filter}
            ORDER BY utilization DESC
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);

        return array_map(function ($row) {
            $row->budgeted_hours = (float) $row->budgeted_hours;
            $row->current_hours = (float) $row->current_hours;
            $row->task_rate = (float) $row->task_rate;
            $row->total_tasks = (int) $row->total_tasks;
            $row->invoiced_tasks = (int) $row->invoiced_tasks;
            $row->uninvoiced_tasks = (int) $row->uninvoiced_tasks;
            $row->running_tasks = (int) $row->running_tasks;
            $row->utilization = (float) $row->utilization;
            $row->hours_remaining = (float) $row->hours_remaining;
            $row->currency_id = (string) $row->currency_id;

            return $row;
        }, $results);
    }

    /**
     * Project Profitability
     *
     * Returns per-project revenue vs cost: invoiced amount from tasks,
     * expenses charged to the project, and net margin.
     *
     * @return array<int, \stdClass>
     */
    public function getProjectProfitability(): array
    {
        $user_filter = $this->is_admin ? '' : 'AND projects.user_id = ' . $this->user->id;
        $user_filter_inv = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;
        $user_filter_exp = $this->is_admin ? '' : 'AND expenses.user_id = ' . $this->user->id;

        $results = DB::select("
            SELECT
                projects.id as project_id,
                projects.name as project_name,
                projects.client_id,
                COALESCE(inv_totals.invoiced_amount, 0) as invoiced_amount,
                COALESCE(exp_totals.expense_amount, 0) as expense_amount,
                ROUND(COALESCE(inv_totals.invoiced_amount, 0) - COALESCE(exp_totals.expense_amount, 0), 2) as net_margin,
                ROUND(
                    CASE WHEN COALESCE(inv_totals.invoiced_amount, 0) > 0
                        THEN (COALESCE(inv_totals.invoiced_amount, 0) - COALESCE(exp_totals.expense_amount, 0))
                             / inv_totals.invoiced_amount
                        ELSE 0
                    END, 4
                ) as margin_ratio,
                IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) AS currency_id
            FROM projects
            JOIN clients
                ON clients.id = projects.client_id
                AND clients.is_deleted = 0
            LEFT JOIN (
                SELECT
                    invoices.project_id,
                    SUM(invoices.amount) as invoiced_amount
                FROM invoices
                WHERE invoices.is_deleted = 0
                AND invoices.status_id IN (2, 3, 4)
                AND invoices.project_id IS NOT NULL
                {$user_filter_inv}
                GROUP BY invoices.project_id
            ) as inv_totals
                ON inv_totals.project_id = projects.id
            LEFT JOIN (
                SELECT
                    expenses.project_id,
                    SUM(expenses.amount) as expense_amount
                FROM expenses
                WHERE expenses.is_deleted = 0
                AND expenses.project_id IS NOT NULL
                {$user_filter_exp}
                GROUP BY expenses.project_id
            ) as exp_totals
                ON exp_totals.project_id = projects.id
            WHERE projects.company_id = :company_id
            AND projects.is_deleted = 0
            {$user_filter}
            ORDER BY net_margin DESC
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);

        return array_map(function ($row) {
            $row->invoiced_amount = (float) $row->invoiced_amount;
            $row->expense_amount = (float) $row->expense_amount;
            $row->net_margin = (float) $row->net_margin;
            $row->margin_ratio = (float) $row->margin_ratio;
            $row->currency_id = (string) $row->currency_id;

            return $row;
        }, $results);
    }
}
