<?php

namespace App\Ninja\Reports;

use App\Models\Payment;
use Auth;

class PaymentReport extends AbstractReport
{
    public $columns = [
        'client',
        'invoice_number',
        'invoice_date',
        'amount',
        'payment_date',
        'paid',
        'method',
    ];

    public function run()
    {
        $account = Auth::user()->account;

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
                        ->with('client.contacts', 'invoice', 'payment_type', 'account_gateway.gateway')
                        ->where('payment_date', '>=', $this->startDate)
                        ->where('payment_date', '<=', $this->endDate);

        foreach ($payments->get() as $payment) {
            $invoice = $payment->invoice;
            $client = $payment->client;
            $this->data[] = [
                $this->isExport ? $client->getDisplayName() : $client->present()->link,
                $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                $invoice->present()->invoice_date,
                $account->formatMoney($invoice->amount, $client),
                $payment->present()->payment_date,
                $account->formatMoney($payment->getCompletedAmount(), $client),
                $payment->present()->method,
            ];

            $this->addToTotals($client->currency_id, 'amount', $invoice->amount);
            $this->addToTotals($client->currency_id, 'paid', $payment->getCompletedAmount());
        }
    }
}
