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

use App\Models\Credit;
use Illuminate\Support\Facades\DB;

/**
 * Class ChartQueries.
 */
trait ChartQueries
{
    /**
     * Expenses
     */
    public function getExpenseQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND expenses.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
            SUM(CASE
                WHEN expenses.uses_inclusive_taxes = 0 THEN
                    expenses.amount +
                    (COALESCE(expenses.tax_amount1, 0) + COALESCE(expenses.tax_amount2, 0) + COALESCE(expenses.tax_amount3, 0)) +
                    (
                        (expenses.amount * COALESCE(expenses.tax_rate1, 0)/100) +
                        (expenses.amount * COALESCE(expenses.tax_rate2, 0)/100) +
                        (expenses.amount * COALESCE(expenses.tax_rate3, 0)/100)
                    )
                ELSE expenses.amount
            END) as amount,
            IFNULL(expenses.currency_id, :company_currency) as currency_id
            FROM expenses
            LEFT JOIN clients
            ON clients.id = expenses.client_id
            LEFT JOIN vendors
            ON vendors.id = expenses.vendor_id
            WHERE expenses.is_deleted = 0
            AND expenses.company_id = :company_id
            AND (expenses.date BETWEEN :start_date AND :end_date)
            {$user_filter}
            AND (clients.id IS NULL OR clients.is_deleted = 0)
            AND (vendors.id IS NULL OR vendors.is_deleted = 0)
            GROUP BY currency_id
        ", ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }

    public function getAggregateExpenseQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND expenses.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
            SUM(
                CASE
                    WHEN expenses.currency_id = :company_currency THEN
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
                        END
                    ELSE
                        (CASE
                            WHEN expenses.uses_inclusive_taxes = 0 THEN
                                expenses.amount +
                                (COALESCE(expenses.tax_amount1, 0) + COALESCE(expenses.tax_amount2, 0) + COALESCE(expenses.tax_amount3, 0)) +
                                (
                                    (expenses.amount * COALESCE(expenses.tax_rate1, 0)/100) +
                                    (expenses.amount * COALESCE(expenses.tax_rate2, 0)/100) +
                                    (expenses.amount * COALESCE(expenses.tax_rate3, 0)/100)
                                )
                            ELSE expenses.amount
                        END) * COALESCE(NULLIF(expenses.exchange_rate, 0), 1)
                END
            ) AS amount
            FROM expenses
            LEFT JOIN clients
            ON clients.id = expenses.client_id
            LEFT JOIN vendors
            ON vendors.id = expenses.vendor_id
            WHERE expenses.is_deleted = 0
            AND expenses.company_id = :company_id
            AND (expenses.date BETWEEN :start_date AND :end_date)
            {$user_filter}
            AND (clients.id IS NULL OR clients.is_deleted = 0)
            AND (vendors.id IS NULL OR vendors.is_deleted = 0)
        ", ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }

    public function getAggregateExpenseChartQuery($start_date, $end_date)
    {

        $user_filter = $this->is_admin ? '' : 'AND expenses.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
            SUM(
                CASE 
                    WHEN expenses.currency_id = :company_currency THEN 
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
                        END
                    ELSE 
                        (CASE 
                            WHEN expenses.uses_inclusive_taxes = 0 THEN 
                                expenses.amount + 
                                (COALESCE(expenses.tax_amount1, 0) + COALESCE(expenses.tax_amount2, 0) + COALESCE(expenses.tax_amount3, 0)) +
                                (
                                    (expenses.amount * COALESCE(expenses.tax_rate1, 0)/100) +
                                    (expenses.amount * COALESCE(expenses.tax_rate2, 0)/100) +
                                    (expenses.amount * COALESCE(expenses.tax_rate3, 0)/100)
                                )   
                            ELSE expenses.amount 
                        END) * COALESCE(NULLIF(expenses.exchange_rate, 0), 1)
                END
            ) AS total,
            expenses.date
            FROM expenses
            LEFT JOIN clients
            ON clients.id = expenses.client_id
            LEFT JOIN vendors
            ON vendors.id = expenses.vendor_id
            WHERE (expenses.date BETWEEN :start_date AND :end_date)
            AND expenses.company_id = :company_id
            AND expenses.is_deleted = 0
            {$user_filter}
            AND (clients.id IS NULL OR clients.is_deleted = 0)
            AND (vendors.id IS NULL OR vendors.is_deleted = 0)
            GROUP BY expenses.date
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    public function getExpenseChartQuery($start_date, $end_date, $currency_id)
    {

        $user_filter = $this->is_admin ? '' : 'AND expenses.user_id = ' . $this->user->id;


        return DB::select("
                    SELECT
                    SUM(
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
                        END
                    ) as total,
                    expenses.date
                    FROM expenses
                    LEFT JOIN clients
                    ON clients.id = expenses.client_id
                    LEFT JOIN vendors
                    ON vendors.id = expenses.vendor_id
                    WHERE (expenses.date BETWEEN :start_date AND :end_date)
                    AND expenses.company_id = :company_id
                    AND expenses.is_deleted = 0
                    {$user_filter}
                    AND (clients.id IS NULL OR clients.is_deleted = 0)
                    AND (vendors.id IS NULL OR vendors.is_deleted = 0)
                    AND IFNULL(expenses.currency_id, :company_currency) = :currency_id
                    GROUP BY expenses.date
                ", [
            'company_currency' => $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);

    }

    public function getExpenseChartQueryForAllCurrencies(string $start_date, string $end_date): array
    {
        $user_filter = $this->is_admin ? '' : 'AND expenses.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
            IFNULL(expenses.currency_id, :company_currency) as effective_currency_id,
            SUM(
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
                END
            ) as total,
            expenses.date
            FROM expenses
            LEFT JOIN clients
            ON clients.id = expenses.client_id
            LEFT JOIN vendors
            ON vendors.id = expenses.vendor_id
            WHERE (expenses.date BETWEEN :start_date AND :end_date)
            AND expenses.company_id = :company_id
            AND expenses.is_deleted = 0
            {$user_filter}
            AND (clients.id IS NULL OR clients.is_deleted = 0)
            AND (vendors.id IS NULL OR vendors.is_deleted = 0)
            GROUP BY effective_currency_id, expenses.date
            ORDER BY effective_currency_id, expenses.date
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Payments
     */
    public function getPaymentQuery($start_date, $end_date)
    {

        $user_filter = $this->is_admin ? '' : 'AND payments.user_id = ' . $this->user->id;

        return DB::select("
            SELECT sum(payments.amount) as amount,
            IFNULL(payments.currency_id, :company_currency) as currency_id
            FROM payments
            JOIN clients
            ON payments.client_id = clients.id
            AND clients.is_deleted = 0
            WHERE payments.is_deleted = 0
            {$user_filter}
            AND payments.company_id = :company_id
            AND (payments.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    public function getAggregatePaymentQuery($start_date, $end_date)
    {

        $user_filter = $this->is_admin ? '' : 'AND payments.user_id = ' . $this->user->id;

        return DB::select("
            SELECT sum((payments.amount - payments.refunded) / COALESCE(NULLIF(payments.exchange_rate, 0), 1)) as amount,
            IFNULL(payments.currency_id, :company_currency) as currency_id
            FROM payments
            JOIN clients
            ON payments.client_id = clients.id
            AND clients.is_deleted = 0
            WHERE payments.company_id = :company_id
            AND payments.is_deleted = 0
            {$user_filter}
            AND payments.status_id IN (4,5,6)
            AND (payments.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    public function getAggregatePaymentChartQuery($start_date, $end_date)
    {

        $user_filter = $this->is_admin ? '' : 'AND payments.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
            sum((payments.amount - payments.refunded) * COALESCE(NULLIF(payments.exchange_rate, 0), 1)) as total,
            payments.date
            FROM payments
            JOIN clients
            ON payments.client_id = clients.id
            AND clients.is_deleted = 0
            WHERE payments.company_id = :company_id
            AND payments.is_deleted = 0
            {$user_filter}
            AND payments.status_id IN (4,5,6)
            AND (payments.date BETWEEN :start_date AND :end_date)
            GROUP BY payments.date
        ", [
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    public function getPaymentChartQuery($start_date, $end_date, $currency_id)
    {

        $user_filter = $this->is_admin ? '' : 'AND payments.user_id = ' . $this->user->id;


        return DB::select("
            SELECT
            sum(payments.amount - payments.refunded) as total,
            payments.date
            FROM payments
            JOIN clients
            ON payments.client_id = clients.id
            WHERE payments.company_id = :company_id
            AND payments.is_deleted = 0
            AND clients.is_deleted = 0
            {$user_filter}
            AND payments.status_id IN (4,5,6)
            AND (payments.date BETWEEN :start_date AND :end_date)
            AND IFNULL(payments.currency_id, :company_currency) = :currency_id
            GROUP BY payments.date
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);

    }

    public function getPaymentChartQueryForAllCurrencies(string $start_date, string $end_date): array
    {
        $user_filter = $this->is_admin ? '' : 'AND payments.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
            IFNULL(payments.currency_id, :company_currency) as effective_currency_id,
            sum(payments.amount - payments.refunded) as total,
            payments.date
            FROM payments
            JOIN clients
            ON payments.client_id = clients.id
            WHERE payments.company_id = :company_id
            AND payments.is_deleted = 0
            AND clients.is_deleted = 0
            {$user_filter}
            AND payments.status_id IN (4,5,6)
            AND (payments.date BETWEEN :start_date AND :end_date)
            GROUP BY effective_currency_id, payments.date
            ORDER BY effective_currency_id, payments.date
        ", [
            'company_currency' => $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Invoices
     */
    public function getOutstandingQuery($start_date, $end_date)
    {

        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        $status_filter = $this->include_drafts ? 'AND invoices.status_id IN (1,2,3)' : 'AND invoices.status_id IN (2,3)';

        return DB::select("
            SELECT
            sum(invoices.balance) as amount,
            COUNT(*) as outstanding_count, 
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT( clients.settings, '$.currency_id' )) AS SIGNED), :company_currency) AS currency_id
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            {$status_filter}
            AND invoices.company_id = :company_id
            AND clients.is_deleted = 0
            {$user_filter}
            AND invoices.is_deleted = 0

            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        ", ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }

    public function getAggregateOutstandingQuery($start_date, $end_date)
    {

        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;
        $status_filter = $this->include_drafts ? 'AND invoices.status_id IN (1,2,3)' : 'AND invoices.status_id IN (2,3)';
        //AND invoices.balance > 0
        return DB::select("
            SELECT
            SUM(invoices.balance / COALESCE(NULLIF(invoices.exchange_rate, 0), 1)) as amount,
            COUNT(invoices.id) as outstanding_count 
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            {$status_filter}
            AND invoices.company_id = :company_id
            AND clients.is_deleted = 0
            {$user_filter}
            AND invoices.is_deleted = 0
            AND (invoices.date BETWEEN :start_date AND :end_date)
        ", [
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date]);

    }

    public function getAggregateRevenueQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND payments.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
            sum((payments.amount - payments.refunded) *  COALESCE(NULLIF(payments.exchange_rate, 0), 1)) as paid_to_date
            FROM payments
            JOIN clients
            ON payments.client_id=clients.id
            WHERE payments.company_id = :company_id
            AND payments.is_deleted = 0
            AND clients.is_deleted = 0
            {$user_filter}
            AND payments.status_id IN (1,4,5,6)
            AND (payments.date BETWEEN :start_date AND :end_date)
            GROUP BY payments.company_id
        ", ['company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }


    public function getRevenueQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND payments.user_id = ' . $this->user->id;

        return DB::select("
            SELECT
            sum(payments.amount - payments.refunded) as paid_to_date,
            payments.currency_id AS currency_id
            FROM payments
            JOIN clients
            ON payments.client_id=clients.id
            WHERE payments.company_id = :company_id
            AND payments.is_deleted = 0
            AND clients.is_deleted = 0
            {$user_filter}
            AND payments.status_id IN (1,4,5,6)
            AND (payments.date BETWEEN :start_date AND :end_date)
            GROUP BY payments.currency_id
        ", ['company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }


    public function getAggregateInvoicesQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        //AND invoices.amount > 0 @2024-12-03 - allow negative invoices to be included
        $status_filter = $this->include_drafts ? 'AND invoices.status_id IN (1,2,3,4)' : 'AND invoices.status_id IN (2,3,4)';

        return DB::select("
            SELECT
                SUM(invoices.amount / COALESCE(NULLIF(invoices.exchange_rate, 0), 1)) as invoiced_amount
            FROM clients
            JOIN invoices ON invoices.client_id = clients.id
            {$status_filter}
            AND invoices.company_id = :company_id
            {$user_filter}
            
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            AND (invoices.date BETWEEN :start_date AND :end_date)
        ", [
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);

    }


    public function getInvoicesQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        $status_filter = $this->include_drafts ? 'AND invoices.status_id IN (1,2,3,4)' : 'AND invoices.status_id IN (2,3,4)';

        return DB::select("
            SELECT
            sum(invoices.amount) as invoiced_amount,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT( clients.settings, '$.currency_id' )) AS SIGNED), :company_currency) AS currency_id
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            {$status_filter}
            AND invoices.company_id = :company_id
            {$user_filter}
            
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        ", ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]);
    }

    public function getAggregateOutstandingChartQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        $status_filter = $this->include_drafts ? 'AND invoices.status_id IN (1,2,3)' : 'AND invoices.status_id IN (2,3)';

        return DB::select("
            SELECT
                SUM(monthly_total) OVER (ORDER BY date) as total,
                date
            FROM (
                SELECT
                    SUM(invoices.balance / COALESCE(NULLIF(invoices.exchange_rate, 0), 1)) as monthly_total,
                    LAST_DAY(invoices.date) as date
                FROM clients
                JOIN invoices
                ON invoices.client_id = clients.id
                {$status_filter}
                AND invoices.company_id = :company_id
                AND clients.is_deleted = 0
                AND invoices.is_deleted = 0
                {$user_filter}
                AND (invoices.date BETWEEN :start_date AND :end_date)
                GROUP BY LAST_DAY(invoices.date)
            ) monthly_totals
        ", [
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    public function getOutstandingChartQuery($start_date, $end_date, $currency_id)
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        $status_filter = $this->include_drafts ? 'AND invoices.status_id IN (1,2,3)' : 'AND invoices.status_id IN (2,3)';

        return DB::select("
            SELECT
                SUM(monthly_total) OVER (ORDER BY date) as total,
                date
            FROM (
                SELECT
                    sum(invoices.balance) as monthly_total,
                    LAST_DAY(invoices.date) as date
                FROM clients
                JOIN invoices
                ON invoices.client_id = clients.id
                {$status_filter}
                AND invoices.company_id = :company_id
                AND clients.is_deleted = 0
                AND invoices.is_deleted = 0
                {$user_filter}
                AND (invoices.date BETWEEN :start_date AND :end_date)
                AND IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) = :currency_id
                GROUP BY LAST_DAY(invoices.date)
            ) monthly_totals
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    public function getOutstandingChartQueryForAllCurrencies(string $start_date, string $end_date): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        $status_filter = $this->include_drafts ? 'AND invoices.status_id IN (1,2,3)' : 'AND invoices.status_id IN (2,3)';

        return DB::select("
            SELECT
                effective_currency_id,
                SUM(monthly_total) OVER (
                    PARTITION BY effective_currency_id
                    ORDER BY date
                    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                ) as total,
                date
            FROM (
                SELECT
                    IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) as effective_currency_id,
                    sum(invoices.balance) as monthly_total,
                    LAST_DAY(invoices.date) as date
                FROM clients
                JOIN invoices
                ON invoices.client_id = clients.id
                {$status_filter}
                AND invoices.company_id = :company_id
                AND clients.is_deleted = 0
                AND invoices.is_deleted = 0
                {$user_filter}
                AND (invoices.date BETWEEN :start_date AND :end_date)
                GROUP BY effective_currency_id, LAST_DAY(invoices.date)
            ) monthly_totals
            ORDER BY effective_currency_id, date
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }


    public function getAggregateInvoiceChartQuery($start_date, $end_date)
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        $status_filter = $this->include_drafts ? 'AND invoices.status_id IN (1,2,3,4)' : 'AND invoices.status_id IN (2,3,4)';

        return DB::select("
            SELECT
                SUM(invoices.amount / COALESCE(NULLIF(invoices.exchange_rate, 0), 1)) as total,
            invoices.date
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.company_id = :company_id
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            {$user_filter}
            {$status_filter}
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY invoices.date
            ORDER BY invoices.date ASC
        ", [
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    public function getInvoiceChartQuery($start_date, $end_date, $currency_id)
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        $status_filter = $this->include_drafts ? 'AND invoices.status_id IN (1,2,3,4)' : 'AND invoices.status_id IN (2,3,4)';

        return DB::select("
            SELECT
            sum(invoices.amount) as total,
            invoices.date   
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.company_id = :company_id
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            {$user_filter}
            {$status_filter}
            AND (invoices.date BETWEEN :start_date AND :end_date)
            AND IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) = :currency_id
            GROUP BY invoices.date
            ORDER BY invoices.date ASC
            ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);

    }

    public function getInvoiceChartQueryForAllCurrencies(string $start_date, string $end_date): array
    {
        $user_filter = $this->is_admin ? '' : 'AND invoices.user_id = ' . $this->user->id;

        $status_filter = $this->include_drafts ? 'AND invoices.status_id IN (1,2,3,4)' : 'AND invoices.status_id IN (2,3,4)';

        return DB::select("
            SELECT
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) as effective_currency_id,
            sum(invoices.amount) as total,
            invoices.date
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.company_id = :company_id
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            {$user_filter}
            {$status_filter}
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY effective_currency_id, invoices.date
            ORDER BY effective_currency_id, invoices.date
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);
    }

    /**
     * Entity Totals
     *
     * Per currency totals for the entity lists. Archived and deleted records are
     * excluded, as are records of archived or deleted clients, matching the lists.
     */
    public function getClientTotalsQuery(): array
    {
        $user_filter = $this->is_admin ? '' : "AND (clients.user_id = {$this->user->id} OR clients.assigned_user_id = {$this->user->id})";

        return DB::select("
            SELECT
            SUM(clients.balance) as amount,
            COUNT(clients.id) as count,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) as effective_currency_id
            FROM clients
            WHERE clients.company_id = :company_id
            AND clients.is_deleted = 0
            AND clients.deleted_at IS NULL
            {$user_filter}
            GROUP BY effective_currency_id
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    public function getInvoiceTotalsQuery(): array
    {
        $user_filter = $this->is_admin ? '' : "AND (invoices.user_id = {$this->user->id} OR invoices.assigned_user_id = {$this->user->id})";

        return DB::select("
            SELECT
            SUM(invoices.balance) as amount,
            COUNT(invoices.id) as count,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) as effective_currency_id
            FROM clients
            JOIN invoices
            ON invoices.client_id = clients.id
            WHERE invoices.company_id = :company_id
            AND invoices.status_id IN (2,3)
            AND invoices.is_deleted = 0
            AND invoices.deleted_at IS NULL
            AND clients.is_deleted = 0
            AND clients.deleted_at IS NULL
            {$user_filter}
            GROUP BY effective_currency_id
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    public function getQuoteTotalsQuery(): array
    {
        $user_filter = $this->is_admin ? '' : "AND (quotes.user_id = {$this->user->id} OR quotes.assigned_user_id = {$this->user->id})";

        return DB::select("
            SELECT
            SUM(quotes.amount) as amount,
            COUNT(quotes.id) as count,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) as effective_currency_id
            FROM clients
            JOIN quotes
            ON quotes.client_id = clients.id
            WHERE quotes.company_id = :company_id
            AND quotes.status_id IN (2,3)
            AND quotes.invoice_id IS NULL
            AND (quotes.due_date IS NULL OR quotes.due_date >= :today)
            AND quotes.is_deleted = 0
            AND quotes.deleted_at IS NULL
            AND clients.is_deleted = 0
            AND clients.deleted_at IS NULL
            {$user_filter}
            GROUP BY effective_currency_id
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'company_id' => $this->company->id,
            'today' => $this->companyToday(),
        ]);
    }

    public function getCreditTotalsQuery(): array
    {
        $user_filter = $this->is_admin ? '' : "AND (credits.user_id = {$this->user->id} OR credits.assigned_user_id = {$this->user->id})";

        return DB::select("
            SELECT
            SUM(credits.balance) as amount,
            COUNT(credits.id) as count,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) as effective_currency_id
            FROM clients
            JOIN credits
            ON credits.client_id = clients.id
            WHERE credits.company_id = :company_id
            AND credits.status_id IN (2,3)
            AND credits.is_deleted = 0
            AND credits.deleted_at IS NULL
            AND clients.is_deleted = 0
            AND clients.deleted_at IS NULL
            {$user_filter}
            GROUP BY effective_currency_id
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    public function getPaymentTotalsQuery(): array
    {
        $user_filter = $this->is_admin ? '' : "AND (payments.user_id = {$this->user->id} OR payments.assigned_user_id = {$this->user->id})";

        // Unapplied amounts less their refunds. payments.refunded also holds refunds of applied amounts: invoice refunds less the credits
        // they returned - see RefundPayment - and deleted reversal credits, the credit paymentables with no amount - see CreditService::deleteCredit
        return DB::select("
            SELECT
            SUM(payments.amount - payments.applied - payments.refunded + IFNULL(applied_refunds.refunded, 0)) as amount,
            COUNT(payments.id) as count,
            IFNULL(payments.currency_id, :company_currency) as effective_currency_id
            FROM payments
            JOIN clients
            ON payments.client_id = clients.id
            LEFT JOIN (
                SELECT
                paymentables.payment_id,
                SUM(CASE
                    WHEN paymentables.paymentable_type = 'invoices' THEN paymentables.refunded
                    WHEN paymentables.amount = 0 THEN paymentables.refunded
                    ELSE -paymentables.refunded
                END) as refunded
                FROM paymentables
                JOIN payments
                ON payments.id = paymentables.payment_id
                WHERE payments.company_id = :paymentables_company_id
                AND paymentables.paymentable_type IN ('invoices', :credit_type)
                AND paymentables.refunded > 0
                GROUP BY paymentables.payment_id
            ) as applied_refunds
            ON applied_refunds.payment_id = payments.id
            WHERE payments.company_id = :company_id
            AND payments.status_id IN (4,5,6)
            AND payments.amount - payments.applied - payments.refunded + IFNULL(applied_refunds.refunded, 0) > 0
            AND payments.is_deleted = 0
            AND payments.deleted_at IS NULL
            AND clients.is_deleted = 0
            AND clients.deleted_at IS NULL
            {$user_filter}
            GROUP BY effective_currency_id
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'credit_type' => Credit::class,
            'paymentables_company_id' => $this->company->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function getRecurringInvoiceTotalsQuery(): array
    {
        $user_filter = $this->is_admin ? '' : "AND (recurring_invoices.user_id = {$this->user->id} OR recurring_invoices.assigned_user_id = {$this->user->id})";

        return DB::select("
            SELECT
            SUM(recurring_invoices.amount) as amount,
            COUNT(recurring_invoices.id) as count,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT(clients.settings, '$.currency_id')) AS SIGNED), :company_currency) as effective_currency_id
            FROM clients
            JOIN recurring_invoices
            ON recurring_invoices.client_id = clients.id
            WHERE recurring_invoices.company_id = :company_id
            AND recurring_invoices.status_id = 2
            AND recurring_invoices.is_deleted = 0
            AND recurring_invoices.deleted_at IS NULL
            AND clients.is_deleted = 0
            AND clients.deleted_at IS NULL
            {$user_filter}
            GROUP BY effective_currency_id
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    public function getPurchaseOrderTotalsQuery(): array
    {
        $user_filter = $this->is_admin ? '' : "AND (purchase_orders.user_id = {$this->user->id} OR purchase_orders.assigned_user_id = {$this->user->id})";

        // Purchase orders are in the vendor currency - see VendorHtmlEngine
        return DB::select("
            SELECT
            SUM(purchase_orders.balance) as amount,
            COUNT(purchase_orders.id) as count,
            IFNULL(vendors.currency_id, :company_currency) as effective_currency_id
            FROM purchase_orders
            LEFT JOIN vendors
            ON vendors.id = purchase_orders.vendor_id
            WHERE purchase_orders.company_id = :company_id
            AND purchase_orders.status_id IN (2,3)
            AND purchase_orders.is_deleted = 0
            AND purchase_orders.deleted_at IS NULL
            AND (vendors.id IS NULL OR (vendors.is_deleted = 0 AND vendors.deleted_at IS NULL))
            {$user_filter}
            GROUP BY effective_currency_id
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    public function getExpenseTotalsQuery(): array
    {
        $user_filter = $this->is_admin ? '' : "AND (expenses.user_id = {$this->user->id} OR expenses.assigned_user_id = {$this->user->id})";

        return DB::select("
            SELECT
            SUM({$this->expenseAmountWithTax('expenses')}) as amount,
            COUNT(expenses.id) as count,
            IFNULL(expenses.currency_id, :company_currency) as effective_currency_id
            FROM expenses
            LEFT JOIN clients
            ON clients.id = expenses.client_id
            LEFT JOIN currencies
            ON currencies.id = IFNULL(expenses.currency_id, :precision_currency)
            WHERE expenses.company_id = :company_id
            AND expenses.should_be_invoiced = 1
            AND expenses.invoice_id IS NULL
            AND expenses.is_deleted = 0
            AND expenses.deleted_at IS NULL
            AND (clients.id IS NULL OR (clients.is_deleted = 0 AND clients.deleted_at IS NULL))
            {$user_filter}
            GROUP BY effective_currency_id
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'precision_currency' => (int) $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }

    public function getRecurringExpenseTotalsQuery(): array
    {
        $user_filter = $this->is_admin ? '' : "AND (recurring_expenses.user_id = {$this->user->id} OR recurring_expenses.assigned_user_id = {$this->user->id})";

        // Active schedules with cycles left, as in RecurringExpensesCron (-1 is endless)
        return DB::select("
            SELECT
            SUM({$this->expenseAmountWithTax('recurring_expenses')}) as amount,
            COUNT(recurring_expenses.id) as count,
            IFNULL(recurring_expenses.currency_id, :company_currency) as effective_currency_id
            FROM recurring_expenses
            LEFT JOIN clients
            ON clients.id = recurring_expenses.client_id
            LEFT JOIN currencies
            ON currencies.id = IFNULL(recurring_expenses.currency_id, :precision_currency)
            WHERE recurring_expenses.company_id = :company_id
            AND recurring_expenses.status_id = 2
            AND recurring_expenses.remaining_cycles != 0
            AND recurring_expenses.is_deleted = 0
            AND recurring_expenses.deleted_at IS NULL
            AND (clients.id IS NULL OR (clients.is_deleted = 0 AND clients.deleted_at IS NULL))
            {$user_filter}
            GROUP BY effective_currency_id
        ", [
            'company_currency' => (int) $this->company->settings->currency_id,
            'precision_currency' => (int) $this->company->settings->currency_id,
            'company_id' => $this->company->id,
        ]);
    }
}
