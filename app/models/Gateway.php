<?php

class Gateway extends Eloquent
{
	public $timestamps = false;
	protected $softDelete = false;	

	public function paymentlibrary()
	{
		return $this->belongsTo('PaymentLibrary');
	}
}