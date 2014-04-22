<?php

use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableInterface;
use Zizaco\Confide\ConfideUser;

class User extends ConfideUser implements UserInterface, RemindableInterface
{
	protected $softDelete = true;

    public static $rules = array(
    	/*
    	'username' => 'required|unique:users',
        'password' => 'required|between:6,32|confirmed',
        'password_confirmation' => 'between:6,32',        
        */
    );

    protected $updateRules = array(
    	/*
    	'email' => 'required|unique:users',
		'username' => 'required|unique:users',
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

	public function isPro()
	{
		return $this->account->isPro();
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

	public function getRequestsCount()
	{
		return Session::get(SESSION_COUNTER, 0);
	}

	public function getPopOverText()
	{
		if (!Auth::check())
		{
			return false;
		}

		$count = self::getRequestsCount();
		if ($count == 1 || $count % 5 == 0)
		{
			if (!Utils::isRegistered())
			{
				return trans('texts.sign_up_to_save');
			}
			else if (!Auth::user()->account->name)
			{
				return trans('texts.set_name');
			}
		}

		return false;
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

	public function getMaxNumClients()
	{
		return $this->isPro() ? MAX_NUM_CLIENTS_PRO : MAX_NUM_CLIENTS;
	}

	public function getRememberToken()
	{
	    return $this->remember_token;
	}

	public function setRememberToken($value)
	{
	    $this->remember_token = $value;
	}

	public function getRememberTokenName()
	{
	    return 'remember_token';
	}	
}