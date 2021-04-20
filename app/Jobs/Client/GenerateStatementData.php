<?php

namespace App\Jobs\Client;

use Utils;
use App\Models\InvoiceItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Eloquent;

class GenerateStatementData
{
    public function __construct($client, $options, $contact = false)
    {
        $this->client = $client;
        $this->options = $options;
        $this->contact = $contact;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = $this->client;
        $client->load('contacts');

        $account = $client->account;
        $account->load(['date_format', 'datetime_format']);

        $invoice = new Invoice();
        $invoice->invoice_date = Utils::today();
        $invoice->account = $account;
        $invoice->client = $client;

        $invoice->invoice_items = $this->getInvoices();

        if ($this->options['show_payments']) {
            $payments = $this->getPayments($invoice->invoice_items);
            $invoice->invoice_items = $invoice->invoice_items->merge($payments);
        }

        $invoice->hidePrivateFields();

        return json_encode($invoice);
    }

    private function getInvoices()
    {
        $statusId = intval($this->options['status_id']);

        $invoices = Invoice::with(['client'])
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

        if ($this->contact) {
            $invoices->whereHas('invitations', function ($query) {
                $query->where('contact_id', $this->contact->id);
            });
        }

        $invoices = $invoices->get();
        $data = collect();

        for ($i=0; $i<$invoices->count(); $i++) {
            $invoice = $invoices[$i];
            $item = new InvoiceItem();
            $item->id = $invoice->id;
            $item->product_key = $invoice->invoice_number;
            $item->custom_value1 = $invoice->invoice_date;
            $item->custom_value2 = $invoice->due_date;
            $item->notes = $invoice->amount;
            $item->cost = $invoice->balance;
            $item->qty = 1;
            $item->invoice_item_type_id = 1;
            $data->push($item);
        }

        if ($this->options['show_aging']) {
            $aging = $this->getAging($invoices);
            $data = $data->merge($aging);
        }

        return $data;
    }

    private function getPayments($invoices)
    {
        $payments = Payment::with('invoice', 'payment_type')
            ->withArchived()
            ->whereClientId($this->client->id)
            //->excludeFailed()
            ->where('payment_date', '>=', $this->options['start_date'])
            ->where('payment_date', '<=', $this->options['end_date']);

        if ($this->contact) {
            $payments->whereIn('invoice_id', $invoices->pluck('id'));
        }

        $payments = $payments->get();
        $data = collect();

        for ($i=0; $i<$payments->count(); $i++) {
            $payment = $payments[$i];
            $item = new InvoiceItem();
            $item->product_key = $payment->invoice->invoice_number;
            $item->custom_value1 = $payment->payment_date;
            $item->custom_value2 = $payment->present()->payment_type;
            $item->cost = $payment->getCompletedAmount();
            $item->invoice_item_type_id = 3;
            $item->notes = $payment->transaction_reference ?: ' ';
            $data->push($item);
        }

        return $data;
    }

    private function getAging($invoices)
    {
        $data = collect();
        $ageGroups = [
            'age_group_0' => 0,
            'age_group_30' => 0,
            'age_group_60' => 0,
            'age_group_90' => 0,
            'age_group_120' => 0,
        ];

        foreach ($invoices as $invoice) {
            $age = $invoice->present()->ageGroup;
            $ageGroups[$age] += $invoice->getRequestedAmount();
        }

        $item = new InvoiceItem();
        $item->product_key = $ageGroups['age_group_0'];
        $item->notes = $ageGroups['age_group_30'];
        $item->custom_value1 = $ageGroups['age_group_60'];
        $item->custom_value2 = $ageGroups['age_group_90'];
        $item->cost = $ageGroups['age_group_120'];
        $item->invoice_item_type_id = 4;
        $data->push($item);

        return $data;
    }
}
