<?php

class Credit extends EntityModel
{
	public function invoice()
	{
		return $this->belongsTo('Invoice');
	}

	public function getName()
	{
		return $this->credit_number;
	}

	public function getEntityType()
	{
		return ENTITY_CREDIT;
	}		
}

Credit::created(function($credit)
{
	Activity::creaateCredit($credit);
});