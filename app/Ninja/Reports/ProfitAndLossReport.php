<?php

namespace App\Ninja\Reports;

use Auth;
use App\Models\Payment;
use App\Models\Expense;

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
                        ->with('client.contacts')
                        ->withArchived()
                        ->excludeFailed();

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
                        ->with('client.contacts')
                        ->withArchived();

        foreach ($expenses->get() as $expense) {
            $client = $expense->client;
            $this->data[] = [
                trans('texts.expense'),
                $client ? ($this->isExport ? $client->getDisplayName() : $client->present()->link) : '',
                $expense->present()->amount,
                $expense->present()->expense_date,
                $expense->present()->category,
            ];

            $this->addToTotals($client->currency_id, 'revenue', 0, $expense->present()->month);
            $this->addToTotals($client->currency_id, 'expenses', $expense->amount, $expense->present()->month);
            $this->addToTotals($client->currency_id, 'profit', $expense->amount * -1, $expense->present()->month);
        }


        //$this->addToTotals($client->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
        //$this->addToTotals($client->currency_id, 'amount', $invoice->amount);
        //$this->addToTotals($client->currency_id, 'balance', $invoice->balance);
    }
}
