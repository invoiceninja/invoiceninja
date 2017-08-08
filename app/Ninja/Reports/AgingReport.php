<?php

namespace App\Ninja\Reports;

use App\Models\Client;
use Auth;

class AgingReport extends AbstractReport
{
    public $columns = [
        'client',
        'invoice_number',
        'invoice_date',
        'due_date',
        'age' => ['group-number-30'],
        'amount',
        'balance',
    ];

    public function run()
    {
        $account = Auth::user()->account;

        $clients = Client::scope()
                        ->orderBy('name')
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function ($query) {
                            $query->invoices()
                                  ->whereIsPublic(true)
                                  ->withArchived()
                                  ->where('balance', '>', 0)
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
                    $invoice->present()->due_date,
                    $invoice->present()->age,
                    $account->formatMoney($invoice->amount, $client),
                    $account->formatMoney($invoice->balance, $client),
                ];

                $this->addToTotals($client->currency_id, $invoice->present()->ageGroup, $invoice->balance);

                //$this->addToTotals($client->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
                //$this->addToTotals($client->currency_id, 'amount', $invoice->amount);
                //$this->addToTotals($client->currency_id, 'balance', $invoice->balance);
            }
        }
    }
}
