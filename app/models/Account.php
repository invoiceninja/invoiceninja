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

	public function language()
	{
		return $this->belongsTo('Language');
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

	public function getDisplayName()
	{
		if ($this->name) 
		{
			return $this->name;
		}

		$this->load('users');
		$user = $this->users()->first();
		
		return $user->getDisplayName();
	}

	public function getTimezone()
	{
		if ($this->timezone)
		{
			return $this->timezone->name;
		}
		else
		{
			return 'US/Eastern';
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
		$path = $this->getLogoPath();
		if (!file_exists($path)) {
			return 0;
		}
		list($width, $height) = getimagesize($path);
		return $width;
	}

	public function getLogoHeight()
	{
		$path = $this->getLogoPath();
		if (!file_exists($path)) {
			return 0;
		}
		list($width, $height) = getimagesize($path);
		return $height;
	}

	public function getNextInvoiceNumber()
	{			
		$invoices = Invoice::withTrashed()->scope(false, $this->id)->get(['invoice_number']);

		$max = 0;

		foreach ($invoices as $invoice)
		{
			$number = intval(preg_replace("/[^0-9]/", "", $invoice->invoice_number));
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

	public function getLocale() 
	{
		$language = Language::remember(DEFAULT_QUERY_CACHE)->where('id', '=', $this->account->language_id)->first();		
		return $language->locale;		
	}

	public function loadLocalizationSettings()
	{
		$this->load('timezone', 'date_format', 'datetime_format', 'language');

		Session::put(SESSION_TIMEZONE, $this->timezone ? $this->timezone->name : DEFAULT_TIMEZONE);
		Session::put(SESSION_DATE_FORMAT, $this->date_format ? $this->date_format->format : DEFAULT_DATE_FORMAT);
		Session::put(SESSION_DATE_PICKER_FORMAT, $this->date_format ? $this->date_format->picker_format : DEFAULT_DATE_PICKER_FORMAT);
		Session::put(SESSION_DATETIME_FORMAT, $this->datetime_format ? $this->datetime_format->format : DEFAULT_DATETIME_FORMAT);			
		Session::put(SESSION_CURRENCY, $this->currency_id ? $this->currency_id : DEFAULT_CURRENCY);		
		Session::put(SESSION_LOCALE, $this->language_id ? $this->language->locale : DEFAULT_LOCALE);
	}

	public function getInvoiceLabels()
	{
		$data = [];
		$fields = [ 
			'invoice',  		
  		'invoice_date',
  		'due_date',
  		'invoice_number',
		  'po_number',
		  'discount',
  		'taxes',
  		'tax',
  		'item',
  		'description',
  		'unit_cost',
  		'quantity',
  		'line_total',
  		'subtotal',
  		'paid_to_date',
  		'balance_due',
  		'terms',
  		'your_invoice',
  		'quote',
  		'your_quote',
  		'quote_date',
  		'quote_number',
  		'total'
		];

		foreach ($fields as $field)
		{
			$data[$field] = trans("texts.$field");
		}

		return $data;
	}

	public function isPro()
	{
		if (!Utils::isNinjaProd())
		{
			return true;
		}

		if ($this->account_key == NINJA_ACCOUNT_KEY)
		{
			return true;
		}

		$datePaid = $this->pro_plan_paid;

		if (!$datePaid || $datePaid == '0000-00-00')
		{
			return false;
		}

		$today = new DateTime('now');
		$datePaid = DateTime::createFromFormat('Y-m-d', $datePaid);		
		$interval = $today->diff($datePaid);
		
		return $interval->y == 0;
	}

}