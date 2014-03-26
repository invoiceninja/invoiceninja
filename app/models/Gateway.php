<?php

class Gateway extends Eloquent
{
	public $timestamps = false;
	protected $softDelete = false;	

	public function paymentlibrary()
	{
		return $this->belongsTo('PaymentLibrary', 'payment_library_id');
	}
	
	public function getLogoUrl()
	{
		return '/images/gateways/logo_'.$this->provider.'.png';
	}
}