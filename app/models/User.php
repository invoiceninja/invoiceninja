<?php

use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableInterface;
use Zizaco\Confide\ConfideUser;

class User extends ConfideUser implements UserInterface, RemindableInterface, iPerson 
{

	protected $softDelete = true;
	protected $hidden = array('created_at', 'updated_at', 'deleted_at', 'password', 'confirmation_code', 'registered', 'confirmed');

    public static $rules = array(
    	/*
        'username' => 'required|email|unique:users',
        'email' => 'required|email|unique:users',
        'password' => 'required|between:4,20|confirmed',
        'password_confirmation' => 'between:4,20',
        */
    );

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'users';

	public function account()
	{
		return $this->belongsTo('Account');
	}

	public function theme()
	{
		return $this->belongsTo('Theme');
	}

	public function getPersonType()
	{
		return PERSON_USER;
	}

	/**
	 * Get the unique identifier for the user.
	 *
	 * @return mixed
	 */
	public function getAuthIdentifier()
	{
		return $this->getKey();
	}

	/**
	 * Get the password for the user.
	 *
	 * @return string
	 */
	public function getAuthPassword()
	{
		return $this->password;
	}

	/**
	 * Get the e-mail address where password reminders are sent.
	 *
	 * @return string
	 */
	public function getReminderEmail()
	{
		return $this->email;
	}

	public function getFullName()
	{
		$fullName = $this->first_name . ' ' . $this->last_name;

		if ($fullName == ' ')
		{
			return "Guest";
		}
		else
		{
			return $fullName;
		}
	}	
}