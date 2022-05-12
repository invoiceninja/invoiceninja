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

namespace App\Services\Report;

use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Models\Company;
use App\Models\Expense;
use Illuminate\Support\Carbon;

class ProfitLoss
{
    private bool $is_income_billed = true;

    private bool $is_expense_billed = true;

    private bool $is_tax_included = true;

    private $start_date;

    private $end_date;

    protected CurrencyApi $currency_api;

   /*
        payload variables.

        start_date - Y-m-d
        end_date - Y-m-d
        date_range - 
            all
            last7
            last30
            this_month
            last_month
            this_quarter
            last_quarter
            this_year
            custom
        income_billed - true = Invoiced || false = Payments
        expense_billed - true = Expensed || false = Expenses marked as paid
        include_tax - true tax_included || false - tax_excluded

    */

    protected array $payload;

    protected Company $company;

    public function __construct(Company $company, array $payload)
    {
        $this->currency_api = new CurrencyApi();

        $this->company = $company;

        $this->payload = $payload;

        $this->setBillingReportType();
    }
    
    public function build()
    {
        //get income

            //sift foreign currencies - calculate both converted foreign amounts to native currency and also also group amounts by currency.

        //get expenses


    }


    /*
        //returns an array of objects
        => [
         {#2047
           +"amount": "706.480000",
           +"total_taxes": "35.950000",
           +"currency_id": ""1"",
           +"net_converted_amount": "670.5300000000",
         },
         {#2444
           +"amount": "200.000000",
           +"total_taxes": "0.000000",
           +"currency_id": ""23"",
           +"net_converted_amount": "1.7129479802",
         },
         {#2654
           +"amount": "140.000000",
           +"total_taxes": "40.000000",
           +"currency_id": ""12"",
           +"net_converted_amount": "69.3275024282",
         },
       ]
   */
    private function invoiceIncome()
    {
        return \DB::select( \DB::raw("
            SELECT
            sum(invoices.amount) as amount,
            sum(invoices.total_taxes) as total_taxes,
            sum(invoices.amount - invoices.total_taxes) as net_amount,
            IFNULL(JSON_EXTRACT( settings, '$.currency_id' ), :company_currency) AS currency_id,
            (sum(invoices.amount - invoices.total_taxes) / IFNULL(invoices.exchange_rate, 1)) AS net_converted_amount
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.status_id IN (2,3,4)
            AND invoices.company_id = :company_id
            AND invoices.amount > 0
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        "), ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $this->start_date, 'end_date' => $this->end_date]  );


        //
        // $total = array_reduce( commissionsArray, function ($sum, $entry) {
        //   $sum += $entry->commission;
        //   return $sum;
        // }, 0);
    }

    private function paymentIncome()
    {
        return \DB::select( \DB::raw("
             SELECT 
             SUM(coalesce(payments.amount - payments.refunded,0)) as payments,
             SUM(coalesce(payments.amount - payments.refunded,0)) * IFNULL(payments.exchange_rate ,1) as payments_converted
             FROM clients 
             INNER JOIN
             payments ON 
             clients.id=payments.client_id 
             WHERE payments.status_id IN (1,4,5,6)
             AND clients.is_deleted = false
             AND payments.is_deleted = false
             AND payments.company_id = :company_id
             AND (payments.date BETWEEN :start_date AND :end_date)
             GROUP BY payments.currency_id
             ORDER BY payments.currency_id;
        "), ['company_id' => $this->company->id, 'start_date' => $this->start_date, 'end_date' => $this->end_date]);
    

    }

    private function expenseCalc()
    {

        $expenses = Expense::where('company_id', $this->company->id)
                           ->where('is_deleted', 0)
                           ->withTrashed()
                           ->whereBetween('date', [$this->start_date, $this->end_date])
                           ->cursor();


        return $this->calculateExpenses($expenses);
        
    }


    private function calculateExpenses($expenses)
    {

        $data = [];


        foreach($expenses as $expense)
        {
            $data[] = [
                'total' => $expense->amount,
                'converted_total' => $converted_total = $this->getConvertedTotal($expense->amount, $expense->exchange_rate),
                'tax' => $tax = $this->getTax($expense),
                'net_converted_total' => $expense->uses_inclusive_taxes ? ( $converted_total - $tax ) : $converted_total,
                'category_id' => $expense->category_id,
                'category_name' => $expense->category ? $expense->category->name : "No Category Defined",
            ];

        }

    }

    private function getTax($expense)
    {
        $amount = $expense->amount;

        //is amount tax

        if($expense->calculate_tax_by_amount)
        {
            return $expense->tax_amount1 + $expense->tax_amount2 + $expense->tax_amount3;
        }


        if($expense->uses_inclusive_taxes){

            $inclusive = 0;

            $inclusive += ($amount - ($amount / (1 + ($expense->tax_rate1 / 100))));
            $inclusive += ($amount - ($amount / (1 + ($expense->tax_rate2 / 100))));
            $inclusive += ($amount - ($amount / (1 + ($expense->tax_rate3 / 100))));

            return round($inclusive,2);

        }


        $exclusive = 0;

        $exclusive += $amount * ($expense->tax_rate1 / 100);
        $exclusive += $amount * ($expense->tax_rate2 / 100);
        $exclusive += $amount * ($expense->tax_rate3 / 100);


        return $exclusive;

    }

    private function getConvertedTotal($amount, $exchange_rate = 1)
    {
        return round(($amount * $exchange_rate) ,2);
    }

    private function expenseCalcWithTax()
    {

      return \DB::select( \DB::raw("
            SELECT sum(expenses.amount) as amount,
            IFNULL(expenses.currency_id, :company_currency) as currency_id
            FROM expenses
            WHERE expenses.is_deleted = 0
            AND expenses.company_id = :company_id
            AND (expenses.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        "), ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $this->start_date, 'end_date' => $this->end_date]  );

    }

    private function setBillingReportType()
    {

        if(array_key_exists('income_billed', $this->payload))
            $this->is_income_billed = boolval($this->payload['income_billed']);

        if(array_key_exists('expense_billed', $this->payload))
            $this->is_expense_billed = boolval($this->payload['expense_billed']);

        if(array_key_exists('include_tax', $this->payload))
            $this->is_tax_included = boolval($this->payload['include_tax']);

        return $this;

    }

    private function addDateRange($query)
    {

        $date_range = $this->payload['date_range'];
        
        try{

            $custom_start_date = Carbon::parse($this->payload['start_date']);
            $custom_end_date = Carbon::parse($this->payload['end_date']);    

        }
        catch(\Exception $e){

            $custom_start_date = now()->startOfYear();
            $custom_end_date = now();

        }
        
        switch ($date_range) {

            case 'all':
                $this->start_date = now()->subYears(50);
                $this->end_date = now();
                // return $query;
            case 'last7':
                $this->start_date = now()->subDays(7);
                $this->end_date = now();
                // return $query->whereBetween($this->date_key, [now()->subDays(7), now()])->orderBy($this->date_key, 'ASC');
            case 'last30':
                $this->start_date = now()->subDays(30);
                $this->end_date = now();
                // return $query->whereBetween($this->date_key, [now()->subDays(30), now()])->orderBy($this->date_key, 'ASC');
            case 'this_month':
                $this->start_date = now()->startOfMonth();
                $this->end_date = now();
                //return $query->whereBetween($this->date_key, [now()->startOfMonth(), now()])->orderBy($this->date_key, 'ASC');
            case 'last_month':
                $this->start_date = now()->startOfMonth()->subMonth();
                $this->end_date = now()->startOfMonth()->subMonth()->endOfMonth();
                //return $query->whereBetween($this->date_key, [now()->startOfMonth()->subMonth(), now()->startOfMonth()->subMonth()->endOfMonth()])->orderBy($this->date_key, 'ASC');
            case 'this_quarter':
                $this->start_date = (new \Carbon\Carbon('-3 months'))->firstOfQuarter();
                $this->end_date = (new \Carbon\Carbon('-3 months'))->lastOfQuarter();
                //return $query->whereBetween($this->date_key, [(new \Carbon\Carbon('-3 months'))->firstOfQuarter(), (new \Carbon\Carbon('-3 months'))->lastOfQuarter()])->orderBy($this->date_key, 'ASC');
            case 'last_quarter':
                $this->start_date = (new \Carbon\Carbon('-6 months'))->firstOfQuarter();
                $this->end_date = (new \Carbon\Carbon('-6 months'))->lastOfQuarter();
                //return $query->whereBetween($this->date_key, [(new \Carbon\Carbon('-6 months'))->firstOfQuarter(), (new \Carbon\Carbon('-6 months'))->lastOfQuarter()])->orderBy($this->date_key, 'ASC');
            case 'this_year':
                $this->start_date = now()->startOfYear();
                $this->end_date = now();
                //return $query->whereBetween($this->date_key, [now()->startOfYear(), now()])->orderBy($this->date_key, 'ASC');
            case 'custom':
                $this->start_date = $custom_start_date;
                $this->end_date = $custom_end_date;
                //return $query->whereBetween($this->date_key, [$custom_start_date, $custom_end_date])->orderBy($this->date_key, 'ASC');
            default:
                $this->start_date = now()->startOfYear();
                $this->end_date = now();
                // return $query->whereBetween($this->date_key, [now()->startOfYear(), now()])->orderBy($this->date_key, 'ASC');

        }

    }

}
