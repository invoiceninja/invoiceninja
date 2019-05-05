<?php

namespace App\Policies;

use App\Models\RecurringQuote;
use App\Models\User;

/**
 * Class RecurringQuotePolicy
 * @package App\Policies
 */
class RecurringQuotePolicy extends EntityPolicy
{
	/**
	 *  Checks if the user has create permissions
	 *  
	 * @param  User $user
	 * @return bool
	 */
	public function create(User $user) : bool
	{
		return $user->isAdmin() || $user->hasPermission('create_recurring_quote');
	}

}
