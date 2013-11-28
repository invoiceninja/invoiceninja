<?php

class Invitation extends Eloquent
{
	protected $softDelete = true;

	public function invoice()
	{
		return $this->belongsTo('Invoice');
	}
}

Invitation::created(function($invitation)
{
	Activity::emailInvoice($invitation);
});