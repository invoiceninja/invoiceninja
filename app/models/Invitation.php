<?php

class Invitation extends EntityModel
{
	protected $hidden = array('id', 'created_at', 'updated_at', 'deleted_at', 'viewed_date');
	
	public function invoice()
	{
		return $this->belongsTo('Invoice');
	}

	public function contact()
	{
		return $this->belongsTo('Contact');
	}

	public function user()
	{
		return $this->belongsTo('User');
	}	
}

Invitation::created(function($invitation)
{
	Activity::emailInvoice($invitation);
});