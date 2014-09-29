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
	
	public function getFields()
	{
		$paymentLibrary =  $this->paymentlibrary;
		
		if ($paymentLibrary->id == PAYMENT_LIBRARY_OMNIPAY)
		{
			$fields = Omnipay::create($this->provider)->getDefaultParameters();				
		}
		else 
		{
			$fields = Payment_Utility::load('config', 'drivers/'.strtolower($this->provider));
		}		

		if ($fields == null)
		{
			$fields = array();
		}
		
		return $fields;
	}
	
}