<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Class PaymentPolicy
 * @package App\Policies
 */
class PaymentPolicy extends EntityPolicy
{
	/**
	 *  Checks if the user has create permissions
	 *  
	 * @param  User $user
	 * @return bool
	 */
	public function create(User $user) : bool
	{
		return $user->isAdmin() || $user->hasPermission('create_payment');
	}

}
