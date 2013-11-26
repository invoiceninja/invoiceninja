<?php

class InvoiceItem extends Eloquent
{
	protected $softDelete = true;

	public function invoice()
	{
		return $this->belongsTo('Invoice');
	}	

	public function product()
	{
		return $this->belongsTo('Product');
	}
}