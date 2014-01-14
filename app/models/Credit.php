<?php

class Credit extends EntityModel
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
	}

	public function getEntityType()
	{
		return ENTITY_CREDIT;
	}		
}

Credit::created(function($credit)
{
	Activity::createCredit($credit);
});

Credit::updating(function($credit)
{
	Activity::updateCredit($credit);
});

Credit::deleting(function($credit)
{
	Activity::archiveCredit($credit);
});