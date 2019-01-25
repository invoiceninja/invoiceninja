<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class EntityPolicy
 * @package App\Policies
 */
class EntityPolicy
{
	/**
	 * Fires before any of the custom policy methods
	 *
	 * Only fires if true, if false we continue.....
	 *
	 * Do not use this function!!!! We MUST also check company_id,
	 * 
	 * @param  User $user
	 * @param  $ability
	 * @return bool/void
	 */
	public function before($user, $ability)
	{
	     //if($user->isAdmin())
	     //	return true;
	}

	/**
	 * Checks if the user has edit permissions
	 *
	 * We MUST also check that the user can both edit a entity and also check the entity belongs to the users company!!!!!!
	 * 
	 * @param  User $user
	 * @param  $entity
	 * @return bool
	 */
	public function edit(User $user, $entity) : bool
	{
    
			return ($user->isAdmin() && $entity->company_id == $user->companyId()) 
			|| ($user->hasPermission('edit_' . strtolower(class_basename($entity))) && $entity->company_id == $user->companyId())
			|| $user->owns($entity);
    
	}


	/**
	 *  Checks if the user has view permissions
	 *
	 * We MUST also check that the user can both view a entity and also check the entity belongs to the users company!!!!!!
	 * @param  User $user
	 * @param  $entity
	 * @return bool
	 */
	public function view(User $user, $entity) : bool
	{

			return ($user->isAdmin() && $entity->company_id == $user->companyId()) 
			|| ($user->hasPermission('view_' . strtolower(class_basename($entity))) && $entity->company_id == $user->companyId()) 
			|| $user->owns($entity);
	}


}
