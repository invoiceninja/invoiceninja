<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Chart;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Class ChartQueries.
 */
trait ChartQueries
{

        // $currencies = Payment::withTrashed()
        //     ->where('company_id', $this->company->id)
        //     ->where('is_deleted', 0)
        //     ->distinct()
        //     ->get(['currency_id']);

    public function getRevenueQuery($start_date, $end_date)
    {

        return DB::select( DB::raw("
            SELECT
            sum(invoices.paid_to_date) as paid_to_date,
            IFNULL(JSON_EXTRACT( settings, '$.currency_id' ), :company_currency) AS currency_id
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.status_id IN (3,4)
            AND invoices.company_id = :company_id
            AND invoices.amount > 0
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        "), ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]  );

    }

    public function getOutstandingQuery($start_date, $end_date)
    {

        return DB::select( DB::raw("
            SELECT
            sum(invoices.balance) as balance,
            IFNULL(JSON_EXTRACT( settings, '$.currency_id' ), :company_currency) AS currency_id
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
        "), ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]  );
    
    }

    public function getExpenseQuery($start_date, $end_date)
    {

        return DB::select( DB::raw("
            SELECT sum(expenses.amount) as amount,
            IFNULL(expenses.currency_id, :company_currency) as currency_id
            FROM expenses
            WHERE expenses.is_deleted = 0
            AND expenses.company_id = :company_id
            AND (expenses.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        "), ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]  );
    
    }

    public function getPaymentQuery($start_date, $end_date)
    {
        return DB::select( DB::raw("
            SELECT sum(expenses.amount) as amount,
            IFNULL(expenses.currency_id, :company_currency) as currency_id
            FROM expenses
            WHERE expenses.is_deleted = 0
            AND expenses.company_id = :company_id
            AND (expenses.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        "), ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $start_date, 'end_date' => $end_date]  );
    }

    public function getInvoiceChartQuery($start_date, $end_date, $currency_id)
    {

         return DB::select( DB::raw("
            SELECT
            sum(invoices.amount) as total,
            invoices.date,
            IFNULL(CAST(JSON_EXTRACT( settings, '$.currency_id' ) AS SIGNED), :company_currency) AS currency_id
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.status_id IN (2,3,4)
            AND (invoices.date BETWEEN :start_date AND :end_date)
            AND invoices.company_id = :company_id
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            GROUP BY invoices.date
            HAVING currency_id = :currency_id
        "), [
            'company_currency' => $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id, 
            'start_date' => $start_date, 
            'end_date' => $end_date
        ]);
    }

    public function getPaymentChartQuery($start_date, $end_date, $currency_id)
    {

         return DB::select( DB::raw("
            SELECT
            sum(payments.amount - payments.refunded) as total,
            payments.date,
            IFNULL(payments.currency_id, :company_currency) AS currency_id
            FROM payments
            WHERE payments.status_id IN (4,5,6)
            AND (payments.date BETWEEN :start_date AND :end_date)
            AND payments.company_id = :company_id
            AND payments.is_deleted = 0
            GROUP BY payments.date
            HAVING currency_id = :currency_id
        "), [
            'company_currency' => $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id, 
            'start_date' => $start_date, 
            'end_date' => $end_date
        ]);
    }

    public function getExpenseChartQuery($start_date, $end_date, $currency_id)
    {

         return DB::select( DB::raw("
            SELECT
            sum(expenses.amount) as total,
            expenses.date,
            IFNULL(expenses.currency_id, :company_currency) AS currency_id
            FROM expenses
            WHERE (expenses.date BETWEEN :start_date AND :end_date)
            AND expenses.company_id = :company_id
            AND expenses.is_deleted = 0
            GROUP BY expenses.date
            HAVING currency_id = :currency_id
        "), [
            'company_currency' => $this->company->settings->currency_id,
            'currency_id' => $currency_id,
            'company_id' => $this->company->id, 
            'start_date' => $start_date, 
            'end_date' => $end_date
        ]);
    }


}




/*
    public function payments($accountId, $userId, $viewAll)
    {
        $payments = DB::table('payments')
                    ->leftJoin('clients', 'clients.id', '=', 'payments.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->where('payments.account_id', '=', $accountId)
                    ->where('payments.is_deleted', '=', false)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('clients.is_deleted', '=', false)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->whereNotIn('payments.payment_status_id', [PAYMENT_STATUS_VOIDED, PAYMENT_STATUS_FAILED]);

        if (! $viewAll) {
            $payments = $payments->where('payments.user_id', '=', $userId);
        }

        return $payments->select(['payments.payment_date', DB::raw('(payments.amount - payments.refunded) as amount'), 'invoices.public_id', 'invoices.invoice_number', 'clients.name as client_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'clients.currency_id', 'clients.public_id as client_public_id', 'clients.user_id as client_user_id'])
                    ->orderBy('payments.payment_date', 'desc')
                    ->take(100)
                    ->get();
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








    */