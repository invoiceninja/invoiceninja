<?php

class Invitation extends EntityModel
{
	protected $hidden = array('id', 'account_id', 'user_id', 'contact_id', 'created_at', 'updated_at', 'deleted_at', 'viewed_date');
	
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