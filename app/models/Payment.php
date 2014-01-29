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

	public function account()
	{
		return $this->belongsTo('Account');
	}

	public function contact()
	{
		return $this->belongsTo('Contact');
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

Payment::updating(function($payment)
{
	Activity::updatePayment($payment);
});

Payment::deleting(function($payment)
{
	Activity::archivePayment($payment);
});