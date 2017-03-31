<?php

namespace App\Ninja\Reports;

use App\Models\Client;
use Auth;

class QuoteReport extends AbstractReport
{
    public $columns = [
        'client',
        'quote_number',
        'quote_date',
        'amount',
        'status',
    ];

    public function run()
    {
        $account = Auth::user()->account;
        $status = $this->options['invoice_status'];

        $clients = Client::scope()
                        ->orderBy('name')
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function ($query) use ($status) {
                            if ($status == 'draft') {
                                $query->whereIsPublic(false);
                            }
                            $query->quotes()
                                  ->withArchived()
                                  ->where('invoice_date', '>=', $this->startDate)
                                  ->where('invoice_date', '<=', $this->endDate)
                                  ->with(['invoice_items']);
                        }]);

        foreach ($clients->get() as $client) {
            foreach ($client->invoices as $invoice) {
                $this->data[] = [
                    $this->isExport ? $client->getDisplayName() : $client->present()->link,
                    $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                    $invoice->present()->invoice_date,
                    $account->formatMoney($invoice->amount, $client),
                    $invoice->present()->status(),
                ];

                $this->addToTotals($client->currency_id, 'amount', $invoice->amount);
            }            
        }
    }
}
