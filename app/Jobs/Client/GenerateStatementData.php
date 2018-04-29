<?php

namespace App\Jobs\Client;

use App\Models\Invoice;
use App\Models\Payment;

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

        $invoice = $account->createInvoice(ENTITY_INVOICE);
        $invoice->client = $client;
        $invoice->date_format = $account->date_format ? $account->date_format->format_moment : 'MMM D, YYYY';

        $invoice->invoice_items = $this->getInvoices();

        if ($this->options['show_payments']) {
            $invoice->invoice_items = $invoice->invoice_items->merge($this->getPayments());
        }

        return json_encode($invoice);
    }

    private function getInvoices()
    {
        $statusId = intval($this->options['status_id']);

        $invoices = Invoice::scope()
            ->with(['client'])
            ->invoices()
            ->whereClientId($this->client->id)
            ->whereIsPublic(true)
            ->withArchived()
            ->orderBy('invoice_date', 'asc');

        if ($statusId == INVOICE_STATUS_PAID) {
            $invoices->where('invoice_status_id', '=', INVOICE_STATUS_PAID);
        } elseif ($statusId == INVOICE_STATUS_UNPAID) {
            $invoices->where('invoice_status_id', '!=', INVOICE_STATUS_PAID);
        }

        if ($statusId == INVOICE_STATUS_PAID || ! $statusId) {
            $invoices->where('invoice_date', '>=', $this->options['start_date'])
                    ->where('invoice_date', '<=', $this->options['end_date']);
        }

        return $invoices->get();
    }

    private function getPayments()
    {
        $payments = Payment::scope()
            ->with('invoice', 'payment_type')
            ->withArchived()
            ->whereClientId($this->client->id)
            ->where('payment_date', '>=', $this->options['start_date'])
            ->where('payment_date', '<=', $this->options['end_date']);

        return $payments->get();
    }

    private function getAging()
    {

    }
}
