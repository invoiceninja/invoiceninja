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

	public function isPro()
	{
		if (!Auth::check()) 
		{
			return false;
		}

		return $this->account->pro_plan_paid;
	}

	public function showGreyBackground()
	{
		return !$this->theme_id || in_array($this->theme_id, [2, 3, 5, 6, 7, 8, 10, 11, 12]);
	}

	public function showSignUpPopOver()
	{
		$count = Session::get(SESSION_COUNTER, 0);
		Session::put(SESSION_COUNTER, ++$count);

		return $count == 1 || $count % 7 == 0;
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