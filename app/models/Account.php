<?php

class Account extends Eloquent
{
	protected $softDelete = true;
	protected $hidden = array('ip', 'timezone_id', 'created_at', 'updated_at', 'deleted_at', 'key', 'last_login');

	public function users()
	{
		return $this->hasMany('User');
	}

	public function clients()
	{
		return $this->hasMany('Client');
	}

	public function invoices()
	{
		return $this->hasMany('Invoice');
	}

	public function account_gateways()
	{
		return $this->hasMany('AccountGateway');
	}

	public function country()
	{
		return $this->belongsTo('Country');
	}

	public function timezone()
	{
		return $this->belongsTo('Timezone');
	}

	public function date_format()
	{
		return $this->belongsTo('DateFormat');	
	}

	public function datetime_format()
	{
		return $this->belongsTo('DatetimeFormat');	
	}


	public function isGatewayConfigured($gatewayId = 0)
	{
		if ($gatewayId)
		{
			return $this->getGatewayConfig($gatewayId) != false;
		}
		else
		{
			return count($this->account_gateways) > 0;
		}
	}

	public function getGatewayConfig($gatewayId)
	{
		foreach ($this->account_gateways as $gateway)
		{
			if ($gateway->gateway_id == $gatewayId)
			{
				return $gateway;
			}
		}	

		return false;	
	}

	public function getLogoPath()
	{
		return 'logo/' . $this->account_key . '.jpg';
	}

	public function getLogoWidth()
	{
		list($width, $height) = getimagesize($this->getLogoPath());
		return $width;
	}

	public function getLogoHeight()
	{
		list($width, $height) = getimagesize($this->getLogoPath());
		return $height;	
	}

	public function getNextInvoiceNumber()
	{	
		$order = Invoice::withTrashed()->scope(false, $this->id)->orderBy('invoice_number', 'DESC')->first();

		if ($order) 
		{
			$number = intval($order->invoice_number) + 1;
			return str_pad($number, 4, "0", STR_PAD_LEFT);
		}	
		else
		{
			return DEFAULT_INVOICE_NUMBER;
		}
	}
}