<?php
namespace App\Factory;

use App\Models\Invoice;
use App\Models\Quote;

class CloneQuoteToInvoiceFactory
{
    public function create(Quote $quote, $user_id, $company_id) : ?Invoice
	{
		$invoice = new Invoice();
		$invoice->company_id = $company_id;
		$invoice->client_id = $quote->client_id;
		$invoice->user_id = $user_id;
		$invoice->po_number = $quote->po_number;
		$invoice->footer = $quote->footer;
		return $invoice;
	}
}
