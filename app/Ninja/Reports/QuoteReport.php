<?php

namespace App\Ninja\Reports;

use App\Models\Client;
use Auth;
use Barracuda\ArchiveStream\Archive;
use App\Models\TaxRate;

class QuoteReport extends AbstractReport
{
    public function getColumns()
    {
        $columns = [
            'client' => [],
            'quote_number' => [],
            'quote_date' => [],
            'amount' => [],
            'status' => [],
            'private_notes' => ['columnSelector-false'],
            'user' => ['columnSelector-false'],
            'billing_address' => ['columnSelector-false'],
            'shipping_address' => ['columnSelector-false'],
        ];

        if (TaxRate::scope()->count()) {
            $columns['tax'] = ['columnSelector-false'];
        }

        $account = auth()->user()->account;

        if ($account->customLabel('invoice_text1')) {
            $columns[$account->present()->customLabel('invoice_text1')] = ['columnSelector-false', 'custom'];
        }
        if ($account->customLabel('invoice_text2')) {
            $columns[$account->present()->customLabel('invoice_text2')] = ['columnSelector-false', 'custom'];
        }

        return $columns;
    }

    public function run()
    {
        $account = Auth::user()->account;
        $statusIds = $this->options['status_ids'];
        $exportFormat = $this->options['export_format'];
        $hasTaxRates = TaxRate::scope()->count();
        $subgroup = $this->options['subgroup'];

        $clients = Client::scope()
                        ->orderBy('name')
                        ->withArchived()
                        ->with('contacts', 'user')
                        ->with(['invoices' => function ($query) use ($statusIds) {
                            $query->quotes()
                                  ->withArchived()
                                  ->statusIds($statusIds)
                                  ->where('invoice_date', '>=', $this->startDate)
                                  ->where('invoice_date', '<=', $this->endDate)
                                  ->with(['invoice_items', 'invoice_status']);
                        }]);

        if ($this->isExport && $exportFormat == 'zip') {
            if (! extension_loaded('GMP')) {
                die(trans('texts.gmp_required'));
            }

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
                $row = [
                    $this->isExport ? $client->getDisplayName() : $client->present()->link,
                    $this->isExport ? $invoice->invoice_number : $invoice->present()->link,
                    $this->isExport ? $invoice->invoice_date : $invoice->present()->invoice_date,
                    $account->formatMoney($invoice->amount, $client),
                    $invoice->present()->status(),
                    $invoice->private_notes,
                    $invoice->user->getDisplayName(),
                    trim(str_replace('<br/>', ', ', $client->present()->address()), ', '),
                    trim(str_replace('<br/>', ', ', $client->present()->address(ADDRESS_SHIPPING)), ', '),
                ];

                if ($hasTaxRates) {
                    $row[] = $account->formatMoney($invoice->getTaxTotal(), $client);
                }

                if ($account->customLabel('invoice_text1')) {
                    $row[] = $invoice->custom_text_value1;
                }
                if ($account->customLabel('invoice_text2')) {
                    $row[] = $invoice->custom_text_value2;
                }

                $this->data[] = $row;

                $this->addToTotals($client->currency_id, 'amount', $invoice->amount);

                if ($subgroup == 'status') {
                    $dimension = $invoice->statusLabel();
                } else {
                    $dimension = $this->getDimension($client);
                }

                $this->addChartData($dimension, $invoice->invoice_date, $invoice->amount);
            }
        }
    }
}
