<?php

namespace App\Ninja\Reports;

use App\Models\Client;
use Auth;

class ClientReport extends AbstractReport
{
    public $columns = [
            'client',
            'amount',
            'paid',
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
                            $query->where('invoice_date', '>=', $this->startDate)
                                  ->where('invoice_date', '<=', $this->endDate)
                                  ->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                                  ->where('is_recurring', '=', false)
                                  ->withArchived();
                        }]);

        foreach ($clients->get() as $client) {
            $amount = 0;
            $paid = 0;

            foreach ($client->invoices as $invoice) {
                $amount += $invoice->amount;
                $paid += $invoice->getAmountPaid();
            }

            $this->data[] = [
                $this->isExport ? $client->getDisplayName() : $client->present()->link,
                $account->formatMoney($amount, $client),
                $account->formatMoney($paid, $client),
                $account->formatMoney($amount - $paid, $client),
            ];

            $this->addToTotals($client->currency_id, 'amount', $amount);
            $this->addToTotals($client->currency_id, 'paid', $paid);
            $this->addToTotals($client->currency_id, 'balance', $amount - $paid);
        }
    }
}
