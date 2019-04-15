<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/**
 * Class InvoicePolicy
 * @package App\Policies
 */
class InvoicePolicy extends EntityPolicy
{
	/**
	 *  Checks if the user has create permissions
	 *  
	 * @param  User $user
	 * @return bool
	 */
	public function create(User $user) : bool
	{
		return $user->isAdmin() || $user->hasPermission('create_invoice');
	}

}
