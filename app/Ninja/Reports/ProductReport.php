<?php

namespace App\Ninja\Reports;

use App\Models\Client;
use Auth;
use Utils;

class ProductReport extends AbstractReport
{
    public $columns = [
        'client',
        'invoice_number',
        'invoice_date',
        'product',
        'description',
        'qty',
        'cost',
        //'tax_rate1',
        //'tax_rate2',
    ];

    public function run()
    {
        $account = Auth::user()->account;
        $statusIds = $this->options['status_ids'];

        $clients = Client::scope()
                        ->orderBy('name')
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function ($query) use ($statusIds) {
                            $query->invoices()
                                  ->withArchived()
                                  ->statusIds($statusIds)
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
                        $this->isExport ? $item->notes : $item->present()->notes,
                        Utils::roundSignificant($item->qty, 0),
                        Utils::roundSignificant($item->cost, 2),
                    ];
                }

                //$this->addToTotals($client->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
                //$this->addToTotals($client->currency_id, 'amount', $invoice->amount);
                //$this->addToTotals($client->currency_id, 'balance', $invoice->balance);
            }
        }
    }
}
