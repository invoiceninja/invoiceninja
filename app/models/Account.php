<?php

class Account extends Eloquent
{
	protected $softDelete = true;

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

	public function tax_rates()
	{
		return $this->hasMany('TaxRate');
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

	public function size()
	{
		return $this->belongsTo('Size');	
	}

	public function industry()
	{
		return $this->belongsTo('Industry');
	}

	public function isGatewayConfigured($gatewayId = 0)
	{
		$this->load('account_gateways');

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
		$orders = Invoice::withTrashed()->scope(false, $this->id)->get();

		$max = 0;

		foreach ($orders as $order)
		{
			$number = intval(preg_replace("/[^0-9]/", "", $order->invoice_number));
			$max = max($max, $number);
		}

		if ($max > 0) 
		{
			return str_pad($max+1, 4, "0", STR_PAD_LEFT);
		}	
		else
		{
			return DEFAULT_INVOICE_NUMBER;
		}
	}

	public function loadLocalizationSettings()
	{
		$this->load('timezone', 'date_format', 'datetime_format');

		Session::put(SESSION_TIMEZONE, $this->timezone ? $this->timezone->name : DEFAULT_TIMEZONE);
		Session::put(SESSION_DATE_FORMAT, $this->date_format ? $this->date_format->format : DEFAULT_DATE_FORMAT);
		Session::put(SESSION_DATE_PICKER_FORMAT, $this->date_format ? $this->date_format->picker_format : DEFAULT_DATE_PICKER_FORMAT);
		Session::put(SESSION_DATETIME_FORMAT, $this->datetime_format ? $this->datetime_format->format : DEFAULT_DATETIME_FORMAT);			
		Session::put(SESSION_CURRENCY, $this->currency_id ? $this->currency_id : DEFAULT_CURRENCY);					
	}
}