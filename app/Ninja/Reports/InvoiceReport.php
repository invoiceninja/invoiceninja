<?php

namespace App\Ninja\Reports;

use Auth;
use App\Models\Client;

class InvoiceReport extends AbstractReport
{
    public $columns = [
        'client',
        'invoice_number',
        'invoice_date',
        'amount',
        'payment_date',
        'paid',
        'method'
    ];

    public function run()
    {
        $account = Auth::user()->account;
        $status = $this->options['invoice_status'];

        $clients = Client::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function($query) use ($status) {
                            if ($status == 'draft') {
                                $query->whereIsPublic(false);
                            } elseif ($status == 'unpaid' || $status == 'paid') {
                                $query->whereIsPublic(true);
                            }
                            $query->invoices()
                                  ->withArchived()
                                  ->where('invoice_date', '>=', $this->startDate)
                                  ->where('invoice_date', '<=', $this->endDate)
                                  ->with(['payments' => function($query) {
                                        $query->withArchived()
                                              ->excludeFailed()
                                              ->with('payment_type', 'account_gateway.gateway');
                                  }, 'invoice_items']);
                        }]);

        foreach ($clients->get() as $client) {
            foreach ($client->invoices as $invoice) {

                $payments = count($invoice->payments) ? $invoice->payments : [false];
                foreach ($payments as $payment) {
                    if ( ! $payment && $status == 'paid') {
                        continue;
                    } elseif ($payment && $status == 'unpaid') {
                        continue;
                    }
                    $this->data[] = [
                        $this->isExport ? $client->getDisplayName() : $client->present()->link,
                        $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                        $invoice->present()->invoice_date,
                        $account->formatMoney($invoice->amount, $client),
                        $payment ? $payment->present()->payment_date : '',
                        $payment ? $account->formatMoney($payment->getCompletedAmount(), $client) : '',
                        $payment ? $payment->present()->method : '',
                    ];

                    $this->addToTotals($client->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
                }

                $this->addToTotals($client->currency_id, 'amount', $invoice->amount);
                $this->addToTotals($client->currency_id, 'balance', $invoice->balance);
            }
        }
    }
}
