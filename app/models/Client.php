<?php

class Client extends EntityModel
{
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

	public function payments()
	{
		return $this->hasMany('Payment');
	}

	public function contacts()
	{
		return $this->hasMany('Contact');
	}

	public function country()
	{
		return $this->belongsTo('Country');
	}

	public function currency()
	{
		return $this->belongsTo('Currency');
	}

	public function size()
	{
		return $this->belongsTo('Size');	
	}

	public function industry()
	{
		return $this->belongsTo('Industry');
	}

	public function getTotalCredit()
	{
		return DB::table('credits')
				->where('client_id','=',$this->id)
				->whereNull('deleted_at')
				->sum('balance');
	}

	public function getName()
	{
		return $this->getDisplayName();
	}

	public function getDisplayName()
	{
		if ($this->name) 
		{
			return $this->name;
		}

		$this->load('contacts');
		$contact = $this->contacts()->first();
		
		return $contact->getDisplayName();
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
			$str .= '<i class="fa fa-phone" style="width: 20px"></i>' . Utils::formatPhoneNumber($this->work_phone);
		}

		return $str;
	}

	public function getNotes()
	{
		$str = '';

		if ($this->private_notes)
		{
			$str .= '<i>' . $this->private_notes . '</i>';
		}

		return $str;
	}

	public function getIndustry()
	{
		$str = '';

		if ($this->client_industry)
		{
			$str .= $this->client_industry->name . ' ';
		}

		if ($this->client_size)
		{
			$str .= $this->client_size->name;
		}

		return $str;
	}

	public function getWebsite()
	{
		if (!$this->website)
		{
			return '';
		}

		$link = $this->website;
		$title = $this->website;
		$prefix = 'http://';

		if (strlen($link) > 7 && substr($link, 0, 7) === $prefix) {
			$title = substr($title, 7);
		} else {
			$link = $prefix . $link;
		}

		return link_to($link, $title, array('target'=>'_blank'));
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

/*
Client::created(function($client)
{
	Activity::createClient($client);
});
*/

Client::updating(function($client)
{
	Activity::updateClient($client);
});

Client::deleting(function($client)
{
	Activity::archiveClient($client);
});