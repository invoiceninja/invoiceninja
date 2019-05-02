<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

/**
 * Class QuotePolicy
 * @package App\Policies
 */
class QuotePolicy extends EntityPolicy
{
	/**
	 *  Checks if the user has create permissions
	 *  
	 * @param  User $user
	 * @return bool
	 */
	public function create(User $user) : bool
	{
		return $user->isAdmin() || $user->hasPermission('create_quote');
	}

}
