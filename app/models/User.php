<?php

use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableInterface;
use Zizaco\Confide\ConfideUser;

class User extends ConfideUser implements UserInterface, RemindableInterface
{
	protected $softDelete = true;

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

	public function getDisplayName()
	{
		if ($this->getFullName())
		{
			return $this->getFullName();
		}
		else if ($this->email)
		{
			return $this->email;
		}
		else
		{
			return 'Guest';
		}
	}


	public function getFullName()
	{
		if ($this->first_name || $this->last_name)
		{
			return $this->first_name . ' ' . $this->last_name;
		}
		else
		{
			return '';
		}
	}	

	public function showGreyBackground()
	{
		return !$this->theme_id || in_array($this->theme_id, [2, 3, 5, 6, 7, 8, 10, 11, 12]);
	}

	public function afterSave($success=true, $forced = false)
	{
		if ($this->email)
		{
			return parent::afterSave($success=true, $forced = false);
		}
		else
		{
			return true;
		}	
	}
}