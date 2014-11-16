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

	public function getLink()
	{
		return SITE_URL . '/view/' . $this->invitation_key;
	}
}