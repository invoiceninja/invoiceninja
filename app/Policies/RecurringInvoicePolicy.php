<?php

namespace App\Policies;

use App\Models\RecurringInvoice;
use App\Models\User;

/**
 * Class RecurringInvoiceolicy
 * @package App\Policies
 */
class RecurringInvoicePolicy extends EntityPolicy
{
	/**
	 *  Checks if the user has create permissions
	 *  
	 * @param  User $user
	 * @return bool
	 */
	public function create(User $user) : bool
	{
		return $user->isAdmin() || $user->hasPermission('create_recurring_invoice');
	}

}
