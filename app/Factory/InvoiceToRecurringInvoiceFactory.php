<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Factory;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Invoice;
use App\Models\RecurringInvoice;

class InvoiceToRecurringInvoiceFactory
{

	public static function create(Invoice $invoice) :RecurringInvoice
	{
		$recurring_invoice = new RecurringInvoice;

		$recurring_invoice->status_id = RecurringInvoice::STATUS_DRAFT;
		$recurring_invoice->discount = $invoice->discount;
		$recurring_invoice->invoice_number = '';
		$recurring_invoice->is_amount_discount = $recurringinvoice->is_amount_discount;
		$recurring_invoice->po_number = $recurringinvoice->po_number;
		$recurring_invoice->footer = $recurringinvoice->footer;
		$recurring_invoice->terms = $recurringinvoice->terms;
		$recurring_invoice->public_notes = $recurringinvoice->public_notes;
		$recurring_invoice->private_notes = $recurringinvoice->private_notes;
		$recurring_invoice->invoice_date = date_create()->format('Y-m-d');
		$recurring_invoice->due_date = $recurringinvoice->due_date; //todo calculate based on terms
		$recurring_invoice->is_deleted = $recurringinvoice->is_deleted;
		$recurring_invoice->line_items = $recurringinvoice->line_items;
		$recurring_invoice->settings = $recurringinvoice->settings;
		$recurring_invoice->tax_name1 = $recurringinvoice->tax_name1;
		$recurring_invoice->tax_rate1 = $recurringinvoice->tax_rate1;
		$recurring_invoice->tax_name2 = $recurringinvoice->tax_name2;
		$recurring_invoice->tax_rate2 = $recurringinvoice->tax_rate2;
		$recurring_invoice->custom_value1 = $recurringinvoice->custom_value1;
		$recurring_invoice->custom_value2 = $recurringinvoice->custom_value2;
		$recurring_invoice->custom_value3 = $recurringinvoice->custom_value3;
		$recurring_invoice->custom_value4 = $recurringinvoice->custom_value4;
		$recurring_invoice->amount = $recurringinvoice->amount;
		$recurring_invoice->balance = $recurringinvoice->balance;
		$recurring_invoice->user_id = $recurringinvoice->user_id;
		$recurring_invoice->company_id = $recurringinvoice->company_id;
		$recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
		$recurring_invoice->start_date = null;
		$recurring_invoice->last_sent_date = null;
		$recurring_invoice->next_send_date = null;
		$recurring_invoice->remaining_cycles = 0;

		return $recurring_invoice;
	}

}
