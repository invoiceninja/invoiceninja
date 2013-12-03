<?php

class AccountGateway extends Eloquent
{
	protected $hidden = array('config');

	public function gateway()
	{
		return $this->belongsTo('Gateway');
	}
}