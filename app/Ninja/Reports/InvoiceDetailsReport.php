<?php

namespace App\Ninja\Reports;

use Auth;
use App\Models\Client;

class InvoiceDetailsReport extends AbstractReport
{
    public $columns = [
        'client',
        'invoice_number',
        'invoice_date',
        'product',
        'qty',
        'cost',
        //'tax_rate1',
        //'tax_rate2',
    ];

    public function run()
    {
        $account = Auth::user()->account;
        $status = $this->options['invoice_status'];

        $clients = Client::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function ($query) use ($status) {
                            if ($status == 'draft') {
                                $query->whereIsPublic(false);
                            } elseif ($status == 'unpaid' || $status == 'paid') {
                                $query->whereIsPublic(true);
                            }
                            $query->invoices()
                                  ->withArchived()
                                  ->where('invoice_date', '>=', $this->startDate)
                                  ->where('invoice_date', '<=', $this->endDate)
                                  ->with(['invoice_items']);
                        }]);

        foreach ($clients->get() as $client) {
            foreach ($client->invoices as $invoice) {
                foreach ($invoice->invoice_items as $item) {
                    $this->data[] = [
                        $this->isExport ? $client->getDisplayName() : $client->present()->link,
                        $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                        $invoice->present()->invoice_date,
                        $item->product_key,
                        $item->qty,
                        $account->formatMoney($item->cost, $client),
                    ];
                }

                //$this->addToTotals($client->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
                //$this->addToTotals($client->currency_id, 'amount', $invoice->amount);
                //$this->addToTotals($client->currency_id, 'balance', $invoice->balance);
            }
        }
    }
}
