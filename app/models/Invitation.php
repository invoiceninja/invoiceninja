<?php

class Invitation extends EntityModel
{
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