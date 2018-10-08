<?php

namespace App\Ninja\Reports;

use App\Models\Expense;
use App\Models\Payment;
use Auth;

class ProfitAndLossReport extends AbstractReport
{
    public function getColumns()
    {
        return [
            'type' => [],
            'client' => [],
            'vendor' => [],
            'amount' => [],
            'date' => [],
            'notes' => [],
        ];
    }

    public function run()
    {
        $account = Auth::user()->account;
        $subgroup = $this->options['subgroup'];

        $payments = Payment::scope()
                        ->orderBy('payment_date', 'desc')
                        ->with('client.contacts', 'invoice', 'user')
                        ->withArchived()
                        ->excludeFailed()
                        ->where('payment_date', '>=', $this->startDate)
                        ->where('payment_date', '<=', $this->endDate);

        foreach ($payments->get() as $payment) {
            $client = $payment->client;
            $invoice = $payment->invoice;
            if ($client->is_deleted || $invoice->is_deleted) {
                continue;
            }
            $this->data[] = [
                trans('texts.payment'),
                $client ? ($this->isExport ? $client->getDisplayName() : $client->present()->link) : '',
                '',
                $account->formatMoney($payment->getCompletedAmount(), $client),
                $this->isExport ? $payment->payment_date : $payment->present()->payment_date,
                $payment->present()->method,
            ];

            $this->addToTotals($client->currency_id, 'revenue', $payment->getCompletedAmount(), $payment->present()->month);
            $this->addToTotals($client->currency_id, 'expenses', 0, $payment->present()->month);
            $this->addToTotals($client->currency_id, 'profit', $payment->getCompletedAmount(), $payment->present()->month);

            if ($subgroup == 'type') {
                $dimension = trans('texts.payment');
            } else {
                $dimension = $this->getDimension($payment);
            }
            $this->addChartData($dimension, $payment->payment_date, $payment->getCompletedAmount());
        }

        $expenses = Expense::scope()
                        ->orderBy('expense_date', 'desc')
                        ->with('client.contacts', 'vendor')
                        ->withArchived()
                        ->where('expense_date', '>=', $this->startDate)
                        ->where('expense_date', '<=', $this->endDate);

        foreach ($expenses->get() as $expense) {
            $client = $expense->client;
            $vendor = $expense->vendor;
            $this->data[] = [
                trans('texts.expense'),
                $client ? ($this->isExport ? $client->getDisplayName() : $client->present()->link) : '',
                $vendor ? ($this->isExport ? $vendor->name : $vendor->present()->link) : '',
                '-' . $expense->present()->amount,
                $this->isExport ? $expense->expense_date : $expense->present()->expense_date,
                $expense->present()->category,
            ];

            $this->addToTotals($expense->expense_currency_id, 'revenue', 0, $expense->present()->month);
            $this->addToTotals($expense->expense_currency_id, 'expenses', $expense->amountWithTax(), $expense->present()->month);
            $this->addToTotals($expense->expense_currency_id, 'profit', $expense->amountWithTax() * -1, $expense->present()->month);

            if ($subgroup == 'type') {
                $dimension = trans('texts.expense');
            } else {
                $dimension = $this->getDimension($expense);
            }
            $this->addChartData($dimension, $expense->expense_date, $expense->amountWithTax());
        }

        //$this->addToTotals($client->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
        //$this->addToTotals($client->currency_id, 'amount', $invoice->amount);
        //$this->addToTotals($client->currency_id, 'balance', $invoice->balance);
    }
}
