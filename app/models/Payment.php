<?php

class Payment extends EntityModel
{
	public function invoice()
	{
		return $this->belongsTo('Invoice');
	}

	public function client()
	{
		return $this->belongsTo('Client');
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