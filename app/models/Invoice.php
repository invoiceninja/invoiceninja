<?php

class Invoice extends EntityModel
{
	protected $hidden = array('id', 'created_at', 'updated_at', 'deleted_at', 'viewed_date');

	public function account()
	{
		return $this->belongsTo('Account');
	}

	public function client()
	{
		return $this->belongsTo('Client');
	}

	public function invoice_items()
	{
		return $this->hasMany('InvoiceItem');
	}

	public function invoice_status()
	{
		return $this->belongsTo('InvoiceStatus');
	}

	public function getName()
	{
		return $this->invoice_number;
	}

	public function getEntityType()
	{
		return ENTITY_INVOICE;
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