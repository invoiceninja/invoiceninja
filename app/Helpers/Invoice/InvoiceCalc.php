<?php

namespace App\Helpers\Invoice;

use App\Models\Invoice;

class InvoiceCalc
{

	public function __construct(Invoice $invoice)
	{
		$this->invoice = $invoice;
	}
	
}