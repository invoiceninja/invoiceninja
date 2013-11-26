<?php

class Contact extends Eloquent
{
	protected $softDelete = true;

	public static $fieldFirstName = 'Contact - First Name';
	public static $fieldLastName = 'Contact - Last Name';
	public static $fieldEmail = 'Contact - Email';
	public static $fieldPhone = 'Contact - Phone';

	public function client()
	{
		return $this->belongsTo('Client');
	}

	public function fullName()
	{
		return $this->first_name . ' ' . $this->last_name;
	}

	public function lastLogin()
	{
		if ($this->last_login == '0000-00-00 00:00:00') 
		{
			return '---';
		} 
		else 
		{
			return $this->last_login;
		}
	}
}