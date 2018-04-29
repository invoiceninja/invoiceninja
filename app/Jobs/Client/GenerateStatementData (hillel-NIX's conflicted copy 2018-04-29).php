<?php

namespace App\Jobs\Client;

use App\Models\Invoice;

class GenerateStatementData
{
    public function __construct($client, $options)
    {
        $this->client = $client;
        $this->options = $options;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = $this->client;
        $account = $client->account;

        $options = $this->options;
        $statusId = intval($options['status_id']);
        $startDate = $options['start_date'];
        $endDate = $options['end_date'];

        $invoice = $account->createInvoice(ENTITY_INVOICE);
        $invoice->client = $client;
        $invoice->date_format = $account->date_format ? $account->date_format->format_moment : 'MMM D, YYYY';

        $invoices = Invoice::scope()
            ->with(['client'])
            ->invoices()
            ->whereClientId($client->id)
            ->whereIsPublic(true)
            ->orderBy('invoice_date', 'asc');

        if ($statusId == INVOICE_STATUS_PAID) {
            $invoices->where('invoice_status_id', '=', INVOICE_STATUS_PAID);
        } elseif ($statusId == INVOICE_STATUS_UNPAID) {
            $invoices->where('invoice_status_id', '!=', INVOICE_STATUS_PAID);
        }

        if ($statusId == INVOICE_STATUS_PAID || ! $statusId) {
            $invoices->where('invoice_date', '>=', $startDate)
                    ->where('invoice_date', '<=', $endDate);
        }

        $invoice->invoice_items = $invoices->get();

        return json_encode($invoice);
    }

    private function getInvoices()
    {

    }
}
