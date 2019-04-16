<?php

namespace App\Factory;

use App\Models\Invoice;

class InvoiceFactory
{
	public static function create(int $company_id, int $user_id) :\stdClass
	{
		$invoice = new \stdClass;
		$invoice->invoice_status_id = Invoice::STATUS_DRAFT;
		$invoice->invoice_number = '';
		$invoice->discount = 0;
		$invoice->is_amount_discount = true;
		$invoice->po_number = '';
		$invoice->invoice_date = null;
		$invoice->due_date = null;
		$invoice->is_deleted = false;
		$invoice->line_items = json_encode([]);
		$invoice->settings = json_encode([]); //todo need to embed the settings here
		$invoice->backup = json_encode([]);
		$invoice->tax_name1 = '';
		$invoice->tax_rate1 = 0;
		$invoice->tax_name2 = '';
		$invoice->tax_rate2 = 0;
		$invoice->custom_value1 = '';
		$invoice->custom_value2 = '';
		$invoice->custom_value3 = '';
		$invoice->custom_value4 = '';
		$invoice->amount = 0;
		$invoice->balance = 0;
		$invoice->partial = 0;
		$invoice->user_id = $user_id;
		$invoice->company_id = $company_id;
		
		return $invoice;
	}
}
