<?php

namespace App\Ninja\Reports;

use App\Models\Expense;
use App\Models\Payment;
use Auth;

class ProfitAndLossReport extends AbstractReport
{
    public $columns = [
        'type',
        'client',
        'amount',
        'date',
        'notes',
    ];

    public function run()
    {
        $account = Auth::user()->account;

        $payments = Payment::scope()
                        ->orderBy('payment_date', 'desc')
                        ->with('client.contacts')
                        ->withArchived()
                        ->excludeFailed()
                        ->where('payment_date', '>=', $this->startDate)
                        ->where('payment_date', '<=', $this->endDate);

        foreach ($payments->get() as $payment) {
            $client = $payment->client;
            $this->data[] = [
                trans('texts.payment'),
                $client ? ($this->isExport ? $client->getDisplayName() : $client->present()->link) : '',
                $account->formatMoney($payment->getCompletedAmount(), $client),
                $payment->present()->payment_date,
                $payment->present()->method,
            ];

            $this->addToTotals($client->currency_id, 'revenue', $payment->getCompletedAmount(), $payment->present()->month);
            $this->addToTotals($client->currency_id, 'expenses', 0, $payment->present()->month);
            $this->addToTotals($client->currency_id, 'profit', $payment->getCompletedAmount(), $payment->present()->month);
        }

        $expenses = Expense::scope()
                        ->orderBy('expense_date', 'desc')
                        ->with('client.contacts')
                        ->withArchived()
                        ->where('expense_date', '>=', $this->startDate)
                        ->where('expense_date', '<=', $this->endDate);

        foreach ($expenses->get() as $expense) {
            $client = $expense->client;
            $this->data[] = [
                trans('texts.expense'),
                $client ? ($this->isExport ? $client->getDisplayName() : $client->present()->link) : '',
                $expense->present()->amount,
                $expense->present()->expense_date,
                $expense->present()->category,
            ];

            $this->addToTotals($expense->expense_currency_id, 'revenue', 0, $expense->present()->month);
            $this->addToTotals($expense->expense_currency_id, 'expenses', $expense->amount, $expense->present()->month);
            $this->addToTotals($expense->expense_currency_id, 'profit', $expense->amount * -1, $expense->present()->month);
        }

        //$this->addToTotals($client->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
        //$this->addToTotals($client->currency_id, 'amount', $invoice->amount);
        //$this->addToTotals($client->currency_id, 'balance', $invoice->balance);
    }
}
