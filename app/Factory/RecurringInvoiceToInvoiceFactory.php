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

class RecurringInvoiceToInvoiceFactory
{

	public static function create(RecurringInvoice $recurring_invoice) :Invoice
	{
		$invoice = new Invoice();
		$invoice->status_id = Invoice::STATUS_DRAFT;
		$invoice->discount = $recurring_invoice->discount;
		$invoice->is_amount_discount = $recurringinvoice->is_amount_discount;
		$invoice->po_number = $recurringinvoice->po_number;
		$invoice->footer = $recurringinvoice->footer;
		$invoice->terms = $recurringinvoice->terms;
		$invoice->public_notes = $recurringinvoice->public_notes;
		$invoice->private_notes = $recurringinvoice->private_notes;
		$invoice->invoice_date = date_create()->format('Y-m-d');
		$invoice->due_date = $recurringinvoice->due_date; //todo calculate based on terms
		$invoice->is_deleted = $recurringinvoice->is_deleted;
		$invoice->line_items = $recurringinvoice->line_items;
		$invoice->settings = $recurringinvoice->settings;
		$invoice->backup = json_encode([]);
		$invoice->tax_name1 = $recurringinvoice->tax_name1;
		$invoice->tax_rate1 = $recurringinvoice->tax_rate1;
		$invoice->tax_name2 = $recurringinvoice->tax_name2;
		$invoice->tax_rate2 = $recurringinvoice->tax_rate2;
		$invoice->custom_value1 = $recurringinvoice->custom_value1;
		$invoice->custom_value2 = $recurringinvoice->custom_value2;
		$invoice->custom_value3 = $recurringinvoice->custom_value3;
		$invoice->custom_value4 = $recurringinvoice->custom_value4;
		$invoice->amount = $recurringinvoice->amount;
		$invoice->balance = $recurringinvoice->balance;
		$invoice->user_id = $recurringinvoice->user_id;
		$invoice->company_id = $recurringinvoice->company_id;

		return $invoice;
	}

}
