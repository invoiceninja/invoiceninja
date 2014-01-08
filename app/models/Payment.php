<?php

class Payment extends EntityModel
{
	public function invoice()
	{
		return $this->belongsTo('Invoice');
	}

	public function invitation()
	{
		return $this->belongsTo('Invitation');
	}

	public function client()
	{
		return $this->belongsTo('Client');
	}

	public function getName()
	{
		return '';
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