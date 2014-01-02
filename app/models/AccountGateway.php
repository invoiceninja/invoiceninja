<?php

class AccountGateway extends EntityModel
{
	public function gateway()
	{
		return $this->belongsTo('Gateway');
	}
}