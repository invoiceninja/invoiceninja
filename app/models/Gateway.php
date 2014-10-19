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

	public function getHelp()
	{
		$link = '';

		if ($this->id == GATEWAY_AUTHORIZE_NET || $this->id == GATEWAY_AUTHORIZE_NET_SIM) {
			$link = 'http://reseller.authorize.net/application/?id=5560364';
		} else if ($this->id == GATEWAY_PAYPAL_EXPRESS) {
			$link = 'https://www.paypal.com/us/cgi-bin/webscr?cmd=_login-api-run';
		} else if ($this->id == GATEWAY_TWO_CHECKOUT) {
			$link = 'https://www.2checkout.com/referral?r=2c37ac2298';
		}

		$key = 'texts.gateway_help_' . $this->id;
		$str = trans($key, ['link' => "<a href='$link' target='_blank'>Click here</a>"]);
		return $key != $str ? $str : '';
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