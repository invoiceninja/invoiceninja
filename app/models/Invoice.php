<?php

class Invoice extends Eloquent
{
	protected $softDelete = true;

	public function client()
	{
		return $this->belongsTo('Client');
	}

	public function invoice_items()
	{
		return $this->hasMany('InvoiceItem');
	}

	public function getTotal()
	{
		$total = 0;

		foreach ($this->invoice_items as $invoiceItem)
		{
			$total += $invoiceItem->qty * $invoiceItem->cost;
		}

		return $total;
	}
}

Invoice::created(function($invoice)
{
	Activity::createInvoice($invoice);
});