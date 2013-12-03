<?php

class InvoiceItem extends Eloquent
{
	protected $softDelete = true;
	protected $hidden = array('created_at', 'updated_at', 'deleted_at');

	public function invoice()
	{
		return $this->belongsTo('Invoice');
	}	

	public function product()
	{
		return $this->belongsTo('Product');
	}
}