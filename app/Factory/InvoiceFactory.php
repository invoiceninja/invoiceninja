<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\Invoice;

class InvoiceFactory
{
    public static function create(int $company_id, int $user_id) :Invoice
    {
        $invoice = new Invoice();
        $invoice->status_id = Invoice::STATUS_DRAFT;
        $invoice->number = null;
        $invoice->discount = 0;
        $invoice->is_amount_discount = true;
        $invoice->po_number = '';
        $invoice->footer = '';
        $invoice->terms = '';
        $invoice->public_notes = '';
        $invoice->private_notes = '';
        $invoice->date = now()->format('Y-m-d');
        $invoice->due_date = null;
        $invoice->partial_due_date = null;
        $invoice->is_deleted = false;
        $invoice->line_items = json_encode([]);
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->tax_name2 = '';
        $invoice->tax_rate2 = 0;
        $invoice->tax_name3 = '';
        $invoice->tax_rate3 = 0;
        $invoice->custom_value1 = '';
        $invoice->custom_value2 = '';
        $invoice->custom_value3 = '';
        $invoice->custom_value4 = '';
        $invoice->amount = 0;
        $invoice->balance = 0;
        $invoice->paid_to_date = 0;
        $invoice->partial = 0;
        $invoice->user_id = $user_id;
        $invoice->company_id = $company_id;
        $invoice->recurring_id = null;
        $invoice->exchange_rate = 1;
        
        return $invoice;
    }
}
