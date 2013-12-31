<?php

class AccountGateway extends Eloquent
{
	public function gateway()
	{
		return $this->belongsTo('Gateway');
	}
}