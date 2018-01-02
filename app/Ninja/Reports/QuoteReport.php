<?php

namespace App\Ninja\Reports;

use App\Models\Client;
use Auth;
use Barracuda\ArchiveStream\Archive;

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
        $exportFormat = $this->options['export_format'];

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

        if ($this->isExport && $exportFormat == 'zip') {
            $zip = Archive::instance_by_useragent(date('Y-m-d') . '_' . str_replace(' ', '_', trans('texts.quote_documents')));
            foreach ($clients->get() as $client) {
                foreach ($client->invoices as $invoice) {
                    foreach ($invoice->documents as $document) {
                        $name = sprintf('%s_%s_%s', $invoice->invoice_date ?: date('Y-m-d'), $invoice->present()->titledName, $document->name);
                        $name = str_replace(' ', '_', $name);
                        $zip->add_file($name, $document->getRaw());
                    }
                }
            }
            $zip->finish();
            exit;
        }

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
