<?php

class Client extends Eloquent
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
}