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


}
