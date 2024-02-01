<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\Invoice;
use App\Models\RecurringInvoice;

class InvoiceToRecurringInvoiceFactory
{
    public static function create(Invoice $invoice): RecurringInvoice
    {
        $recurring_invoice = new RecurringInvoice();

        $recurring_invoice->status_id = RecurringInvoice::STATUS_DRAFT;
        $recurring_invoice->discount = $invoice->discount;
        $recurring_invoice->number = '';
        $recurring_invoice->is_amount_discount = $invoice->is_amount_discount;
        $recurring_invoice->po_number = $invoice->po_number;
        $recurring_invoice->footer = $invoice->footer;
        $recurring_invoice->terms = $invoice->terms;
        $recurring_invoice->public_notes = $invoice->public_notes;
        $recurring_invoice->private_notes = $invoice->private_notes;
        $recurring_invoice->date = date_create()->format($invoice->client->date_format());
        $recurring_invoice->due_date = $invoice->due_date; //todo calculate based on terms
        $recurring_invoice->is_deleted = $invoice->is_deleted;
        $recurring_invoice->line_items = $invoice->line_items;
        $recurring_invoice->tax_name1 = $invoice->tax_name1;
        $recurring_invoice->tax_rate1 = $invoice->tax_rate1;
        $recurring_invoice->tax_name2 = $invoice->tax_name2;
        $recurring_invoice->tax_rate2 = $invoice->tax_rate2;
        $recurring_invoice->custom_value1 = $invoice->custom_value1;
        $recurring_invoice->custom_value2 = $invoice->custom_value2;
        $recurring_invoice->custom_value3 = $invoice->custom_value3;
        $recurring_invoice->custom_value4 = $invoice->custom_value4;
        $recurring_invoice->amount = $invoice->amount;
        // $recurring_invoice->balance = $invoice->balance;
        $recurring_invoice->user_id = $invoice->user_id;
        $recurring_invoice->client_id = $invoice->client_id;
        $recurring_invoice->company_id = $invoice->company_id;
        $recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->last_sent_date = null;
        $recurring_invoice->next_send_date = null;
        $recurring_invoice->remaining_cycles = 0;
        $recurring_invoice->paid_to_date = 0;

        return $recurring_invoice;
    }
}
