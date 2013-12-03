<?php

class Payment extends Eloquent implements iEntity 
{
	protected $softDelete = true;

	public function scopeScope($query)
	{
		return $query->whereAccountId(Auth::user()->account_id);
	}

	public function invoice()
	{
		return $this->belongsTo('Invoice');
	}

	public function getName()
	{
		return '';
		//return $this->invoice_number;
	}

	public function getEntityType()
	{
		return ENTITY_PAYMENT;
	}	

}

Payment::created(function($payment)
{
	Activity::createPayment($payment);
});