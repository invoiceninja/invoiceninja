<?php

class Payment extends Eloquent
{
	protected $softDelete = true;

	public function invoice()
	{
		return $this->belongsTo('Invoice');
	}
}

Payment::created(function($payment)
{
	Activity::createPayment($payment);
});