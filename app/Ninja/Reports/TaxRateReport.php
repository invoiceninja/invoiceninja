<?php

namespace App\Ninja\Reports;

use App\Models\Client;
use Auth;

class TaxRateReport extends AbstractReport
{
    public $columns = [
        'tax_name',
        'tax_rate',
        'amount',
        'paid',
    ];

    public function run()
    {
        $account = Auth::user()->account;

        $clients = Client::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function ($query) {
                            $query->with('invoice_items')->withArchived();
                            if ($this->options['date_field'] == FILTER_INVOICE_DATE) {
                                $query->where('invoice_date', '>=', $this->startDate)
                                      ->where('invoice_date', '<=', $this->endDate)
                                      ->with('payments');
                            } else {
                                $query->whereHas('payments', function ($query) {
                                    $query->where('payment_date', '>=', $this->startDate)
                                                  ->where('payment_date', '<=', $this->endDate)
                                                  ->withArchived();
                                })
                                        ->with(['payments' => function ($query) {
                                            $query->where('payment_date', '>=', $this->startDate)
                                                  ->where('payment_date', '<=', $this->endDate)
                                                  ->withArchived();
                                        }]);
                            }
                        }]);

        foreach ($clients->get() as $client) {
            $currencyId = $client->currency_id ?: Auth::user()->account->getCurrencyId();
            $amount = 0;
            $paid = 0;
            $taxTotals = [];

            foreach ($client->invoices as $invoice) {
                foreach ($invoice->getTaxes(true) as $key => $tax) {
                    if (! isset($taxTotals[$currencyId])) {
                        $taxTotals[$currencyId] = [];
                    }
                    if (isset($taxTotals[$currencyId][$key])) {
                        $taxTotals[$currencyId][$key]['amount'] += $tax['amount'];
                        $taxTotals[$currencyId][$key]['paid'] += $tax['paid'];
                    } else {
                        $taxTotals[$currencyId][$key] = $tax;
                    }
                }

                $amount += $invoice->amount;
                $paid += $invoice->getAmountPaid();
            }

            foreach ($taxTotals as $currencyId => $taxes) {
                foreach ($taxes as $tax) {
                    $this->data[] = [
                        $tax['name'],
                        $tax['rate'] . '%',
                        $account->formatMoney($tax['amount'], $client),
                        $account->formatMoney($tax['paid'], $client),
                    ];
                }

                $this->addToTotals($client->currency_id, 'amount', $tax['amount']);
                $this->addToTotals($client->currency_id, 'paid', $tax['paid']);
            }
        }
    }
}
