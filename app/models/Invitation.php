<?php

class Invitation extends Eloquent
{
	protected $softDelete = true;
	protected $hidden = array('created_at', 'updated_at', 'deleted_at');

	public function scopeScope($query)
	{
		return $query->whereAccountId(Auth::user()->account_id);
	}

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