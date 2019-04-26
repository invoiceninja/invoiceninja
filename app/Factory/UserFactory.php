<?php

namespace App\Factory;

use App\Models\User;

class UserFactory
{
	public static function create() :User
	{
		$user = new User;

		$user->first_name = '';
		$user->last_name = '';
		$user->phone = '';
		$user->email = '';
		$user->theme_id = 0;
		$user->failed_logins = 0;
		$user->signature = '';

		return $user;
	}
}

