<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Class ClientPolicy
 * @package App\Policies
 */
class ClientPolicy extends EntityPolicy
{
	/**
	 *  Checks if the user has create permissions
	 *  
	 * @param  User $user
	 * @return bool
	 */
	public function create(User $user) : bool
	{
		return $user->hasPermission('create_client');
	}
	
}
