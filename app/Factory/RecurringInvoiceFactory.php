<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Factory;

use App\Models\RecurringInvoice;

class RecurringInvoiceFactory
{
    public static function create(int $company_id, int $user_id) :RecurringInvoice
    {
        $invoice = new RecurringInvoice();
        $invoice->status_id = RecurringInvoice::STATUS_DRAFT;
        $invoice->discount = 0;
        $invoice->is_amount_discount = true;
        $invoice->po_number = '';
        $invoice->number = '';
        $invoice->footer = '';
        $invoice->terms = '';
        $invoice->public_notes = '';
        $invoice->private_notes = '';
        $invoice->date = null;
        $invoice->due_date = null;
        $invoice->partial_due_date = null;
        $invoice->is_deleted = false;
        $invoice->line_items = json_encode([]);
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->tax_name2 = '';
        $invoice->tax_rate2 = 0;
        $invoice->custom_value1 = 0;
        $invoice->custom_value2 = 0;
        $invoice->custom_value3 = 0;
        $invoice->custom_value4 = 0;
        $invoice->amount = 0;
        $invoice->balance = 0;
        $invoice->partial = 0;
        $invoice->user_id = $user_id;
        $invoice->company_id = $company_id;
        $invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $invoice->last_sent_date = null;
        $invoice->next_send_date = null;
        $invoice->remaining_cycles = 0;

        return $invoice;
    }
}
