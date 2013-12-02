<?php

class Client extends Eloquent implements iEntity 
{
	protected $softDelete = true;

	public static $fieldName = 'Client - Name';
	public static $fieldPhone = 'Client - Phone';
	public static $fieldAddress1 = 'Client - Street';
	public static $fieldAddress2 = 'Client - Apt/Floor';
	public static $fieldCity = 'Client - City';
	public static $fieldState = 'Client - State';
	public static $fieldPostalCode = 'Client - Postal Code';
	public static $fieldNotes = 'Client - Notes';
	public static $fieldCountry = 'Client - Country';

	public function account()
	{
		return $this->belongsTo('Account');
	}

	public function invoices()
	{
		return $this->hasMany('Invoice');
	}

	public function contacts()
	{
		return $this->hasMany('Contact');
	}

	public function country()
	{
		return $this->belongsTo('Country');
	}

	public function getName()
	{
		return $this->name;
	}

	public function getEntityType()
	{
		return ENTITY_CLIENT;
	}

	public function getAddress()
	{
		$str = '';

		if ($this->address1) {
			$str .= $this->address1 . '<br/>';
		}
		if ($this->address2) {
			$str .= $this->address2 . '<br/>';	
		}
		if ($this->city) {
			$str .= $this->city . ', ';	
		}
		if ($this->state) {
			$str .= $this->state . ' ';	
		}
		if ($this->postal_code) {
			$str .= $this->postal_code;
		}
		if ($this->country) {
			$str .= '<br/>' . $this->country->name;			
		}

		if ($str)
		{
			$str = '<p>' . $str . '</p>';
		}

		return $str;
	}

	public function getPhone()
	{
		$str = '';

		if ($this->work_phone)
		{
			$str .= '<i class="fa fa-phone" style="width: 20px"></i>' . $this->work_phone;
		}

		return $str;
	}

	public function getNotes()
	{
		$str = '';

		if ($this->notes)
		{
			$str .= '<i>' . $this->notes . '</i>';
		}

		return $str;
	}

	public function getDateCreated()
	{		
		if ($this->created_at == '0000-00-00 00:00:00') 
		{
			return '---';
		} 
		else 
		{
			return $this->created_at->format('m/d/y h:i a');
		}
	}
}

Client::created(function($client)
{
	Activity::createClient($client);
});