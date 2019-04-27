<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Class UserPolicy
 * @package App\Policies
 */
class UserPolicy extends EntityPolicy
{
	/**
	 *  Checks if the user has create permissions
	 *  
	 * @param  User $user
	 * @return bool
	 */
	public function create(User $user) : bool
	{
		return $user->isAdmin() || $user->hasPermission('create_user');
	}

}
