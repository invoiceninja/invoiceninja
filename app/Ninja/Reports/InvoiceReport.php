<?php

namespace App\Ninja\Reports;

use App\Models\Client;
use Auth;
use Barracuda\ArchiveStream\Archive;
use App\Models\TaxRate;

class InvoiceReport extends AbstractReport
{
    public function getColumns()
    {
        $columns = [
            'client' => [],
            'invoice_number' => [],
            'invoice_date' => [],
            'amount' => [],
            'status' => [],
            'payment_date' => [],
            'paid' => [],
            'method' => [],
            'private_notes' => ['columnSelector-false'],
        ];

        if (TaxRate::scope()->count()) {
            $columns['tax'] = ['columnSelector-false'];
        }

        $account = auth()->user()->account;

        if ($account->custom_invoice_text_label1) {
            $columns[$account->custom_invoice_text_label1] = ['columnSelector-false', 'custom'];
        }
        if ($account->custom_invoice_text_label1) {
            $columns[$account->custom_invoice_text_label1] = ['columnSelector-false', 'custom'];
        }

        return $columns;
    }

    public function run()
    {
        $account = Auth::user()->account;
        $statusIds = $this->options['status_ids'];
        $exportFormat = $this->options['export_format'];

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
                                  ->with(['payments' => function ($query) {
                                      $query->withArchived()
                                              ->excludeFailed()
                                              ->with('payment_type', 'account_gateway.gateway');
                                  }, 'invoice_items']);
                        }]);


        if ($this->isExport && $exportFormat == 'zip') {
            $zip = Archive::instance_by_useragent(date('Y-m-d') . '_' . str_replace(' ', '_', trans('texts.invoice_documents')));
            foreach ($clients->get() as $client) {
                foreach ($client->invoices as $invoice) {
                    foreach ($invoice->documents as $document) {
                        $name = sprintf('%s_%s_%s', $invoice->invoice_date ?: date('Y-m-d'), $invoice->present()->titledName, $document->name);
                        $zip->add_file($name, $document->getRaw());
                    }
                }
            }
            $zip->finish();
            exit;
        }

        foreach ($clients->get() as $client) {
            foreach ($client->invoices as $invoice) {
                $isFirst = true;
                $payments = $invoice->payments->count() ? $invoice->payments : [false];
                foreach ($payments as $payment) {
                    $row = [
                        $this->isExport ? $client->getDisplayName() : $client->present()->link,
                        $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                        $invoice->present()->invoice_date,
                        $isFirst ? $account->formatMoney($invoice->amount, $client) : '',
                        $invoice->statusLabel(),
                        $payment ? $payment->present()->payment_date : '',
                        $payment ? $account->formatMoney($payment->getCompletedAmount(), $client) : '',
                        $payment ? $payment->present()->method : '',
                        $invoice->private_notes,
                    ];

                    if (TaxRate::scope()->count()) {
                        $row[] = $invoice->getTaxTotal();
                    }

                    if ($account->custom_invoice_text_label1) {
                        $row[] = $invoice->custom_text_value1;
                    }
                    if ($account->custom_invoice_text_label2) {
                        $row[] = $invoice->custom_text_value2;
                    }

                    $this->data[] = $row;

                    $this->addToTotals($client->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
                    $isFirst = false;
                }

                $this->addToTotals($client->currency_id, 'amount', $invoice->amount);
                $this->addToTotals($client->currency_id, 'balance', $invoice->balance);
            }
        }
    }
}
