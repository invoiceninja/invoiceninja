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

use App\Models\Invoice;
use App\Models\Quote;

class CloneInvoiceToQuoteFactory
{
	public static function create(Invoice $invoice, $user_id) : ?Quote
	{

		$quote = new Quote();
		$quote->client_id = $invoice->client_id;
		$quote->user_id = $user_id;
		$quote->company_id = $invoice->company_id;
		$quote->discount = $invoice->discount;
		$quote->is_amount_discount = $invoice->is_amount_discount;
		$quote->po_number = $invoice->po_number;
		$quote->is_deleted = false;
		$quote->backup = null;
		$quote->footer = $invoice->footer;
		$quote->public_notes = $invoice->public_notes;
		$quote->private_notes = $invoice->private_notes;
		$quote->terms = $invoice->terms;
		$quote->tax_name1 = $invoice->tax_name1;
		$quote->tax_rate1 = $invoice->tax_rate1;
		$quote->tax_name2 = $invoice->tax_name2;
		$quote->tax_rate2 = $invoice->tax_rate2;
		$quote->custom_value1 = $invoice->custom_value1;
		$quote->custom_value2 = $invoice->custom_value2;
		$quote->custom_value3 = $invoice->custom_value3;
		$quote->custom_value4 = $invoice->custom_value4;
		$quote->amount = $invoice->amount;
		$quote->balance = $invoice->balance;
		$quote->partial = $invoice->partial;
		$quote->partial_due_date = $invoice->partial_due_date;
		$quote->last_viewed = $invoice->last_viewed;

		$quote->status_id = Quote::STATUS_DRAFT;
		$quote->quote_number = '';
		$quote->quote_date = null;
		$quote->due_date = null;
		$quote->partial_due_date = null;
		$quote->balance = $invoice->amount;
		$quote->settings = $invoice->settings;
		$quote->line_items = $invoice->line_items;

		return $quote;
	}

} 
