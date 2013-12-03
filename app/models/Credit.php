<?php

class Credit extends Eloquent implements iEntity 
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