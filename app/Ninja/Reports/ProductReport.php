<?php

namespace App\Ninja\Reports;

use Auth;
use App\Models\Client;

class ProductReport extends AbstractReport
{
    public $columns = [
        'client',
        'invoice_number',
        'invoice_date',
        'quantity',
        'product',
    ];

    public function run()
    {
        $account = Auth::user()->account;

        $clients = Client::scope()
                        ->withTrashed()
                        ->with('contacts')
                        ->where('is_deleted', '=', false)
                        ->with(['invoices' => function($query) {
                            $query->where('invoice_date', '>=', $this->startDate)
                                  ->where('invoice_date', '<=', $this->endDate)
                                  ->where('is_deleted', '=', false)
                                  ->where('is_recurring', '=', false)
                                  ->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                                  ->with(['invoice_items'])
                                  ->withTrashed();
                        }]);

        foreach ($clients->get() as $client) {
            foreach ($client->invoices as $invoice) {

                foreach ($invoice->invoice_items as $invoiceItem) {
                    $this->data[] = [
                        $this->isExport ? $client->getDisplayName() : $client->present()->link,
                        $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                        $invoice->present()->invoice_date,
                        round($invoiceItem->qty, 2),
                        $invoiceItem->product_key,
                    ];
                    //$reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'paid', $payment ? $payment->amount : 0);
                }

                //$reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'amount', $invoice->amount);
                //$reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'balance', $invoice->balance);
            }
        }
    }
}
