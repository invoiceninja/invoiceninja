<?php

namespace App\Factory;

use App\Models\Invoice;

class CloneInvoiceFactory
{
	public static function create(Invoice $invoice, $user_id) : ?Invoice
	{
		$clone_invoice = $invoice->replicate();
		$clone_invoice->status_id = Invoice::STATUS_DRAFT;
		$clone_invoice->invoice_number = '';
		$clone_invoice->invoice_date = null;
		$clone_invoice->due_date = null;
		$clone_invoice->partial_due_date = null;
		$clone_invoice->user_id = $user_id;
		$clone_invoice->balance = $invoice->amount;
		$clone_invoice->settings = $invoice->settings;
		$clone_invoice->line_items = $invoice->line_items;
		$clone_invoice->backup = null;
		
		return $clone_invoice;
	}

} 