<?php

namespace App\Helpers\Invoice;

use App\Models\Invoice;

class InvoiceHelper 
{
	public function __construct(Invoice $invoice)
	{
		$this->invoice = $invoice;
	}
}