<?php

namespace App\Ninja\Reports;

use App\Models\Payment;
use Auth;
use Utils;

class PaymentReport extends AbstractReport
{
    public function getColumns()
    {
        return [
            'client' => [],
            'invoice_number' => [],
            'invoice_date' => [],
            'amount' => [],
            'payment_date' => [],
            'paid' => [],
            'method' => [],
            'private_notes' => ['columnSelector-false'],
            'user' => ['columnSelector-false'],
        ];
    }

    public function run()
    {
        $account = Auth::user()->account;
        $currencyType = $this->options['currency_type'];
        $invoiceMap = [];
        $subgroup = $this->options['subgroup'];

        $payments = Payment::scope()
                        ->orderBy('payment_date', 'desc')
                        ->withArchived()
                        ->excludeFailed()
                        ->whereHas('client', function ($query) {
                            $query->where('is_deleted', '=', false);
                        })
                        ->whereHas('invoice', function ($query) {
                            $query->where('is_deleted', '=', false);
                        })
                        ->with('client.contacts', 'invoice', 'payment_type', 'account_gateway.gateway', 'user')
                        ->where('payment_date', '>=', $this->startDate)
                        ->where('payment_date', '<=', $this->endDate);

        $lastInvoiceId = 0;
        foreach ($payments->get() as $payment) {
            $invoice = $payment->invoice;
            $client = $payment->client;
            $amount = $payment->getCompletedAmount();

            if ($currencyType == 'converted') {
                $amount *= $payment->exchange_rate;
                $this->addToTotals($payment->exchange_currency_id, 'paid', $amount);
                $amount = Utils::formatMoney($amount, $payment->exchange_currency_id);
            } else {
                $this->addToTotals($client->currency_id, 'paid', $amount);
                $amount = $account->formatMoney($amount, $client);
            }

            $this->data[] = [
                $this->isExport ? $client->getDisplayName() : $client->present()->link,
                $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                $this->isExport ? $invoice->invoice_date : $invoice->present()->invoice_date,
                $lastInvoiceId == $invoice->id ? '' : $account->formatMoney($invoice->amount, $client),
                $this->isExport ? $payment->payment_date : $payment->present()->payment_date,
                $amount,
                $payment->present()->method,
                $payment->private_notes,
                $payment->user->getDisplayName(),
            ];

            if (! isset($invoiceMap[$invoice->id])) {
                $invoiceMap[$invoice->id] = true;

                if ($currencyType == 'converted') {
                    $this->addToTotals($payment->exchange_currency_id, 'amount', $invoice->amount * $payment->exchange_rate);
                } else {
                    $this->addToTotals($client->currency_id, 'amount', $invoice->amount);
                }
            }

            if ($subgroup == 'method') {
                $dimension = $payment->present()->method;
            } else {
                $dimension = $this->getDimension($payment);
            }

            $convertedAmount = $currencyType == 'converted' ? ($invoice->amount * $payment->exchange_rate) : $invoice->amount;
            $this->addChartData($dimension, $payment->payment_date, $convertedAmount);

            $lastInvoiceId = $invoice->id;
        }
    }
}
