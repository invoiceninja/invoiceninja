<?php

namespace App\Ninja\Reports;

use App\Models\Expense;
use Auth;
use Utils;

class ExpenseReport extends AbstractReport
{
    public $columns = [
        'vendor',
        'client',
        'date',
        'category',
        'expense_amount',
    ];

    public function run()
    {
        $account = Auth::user()->account;

        $expenses = Expense::scope()
                        ->withArchived()
                        ->with('client.contacts', 'vendor')
                        ->where('expense_date', '>=', $this->startDate)
                        ->where('expense_date', '<=', $this->endDate);

        foreach ($expenses->get() as $expense) {
            $amount = $expense->amountWithTax();

            $this->data[] = [
                $expense->vendor ? ($this->isExport ? $expense->vendor->name : $expense->vendor->present()->link) : '',
                $expense->client ? ($this->isExport ? $expense->client->getDisplayName() : $expense->client->present()->link) : '',
                $expense->present()->expense_date,
                $expense->present()->category,
                Utils::formatMoney($amount, $expense->currency_id),
            ];

            $this->addToTotals($expense->expense_currency_id, 'amount', $amount);
            $this->addToTotals($expense->invoice_currency_id, 'amount', 0);
        }
    }
}
