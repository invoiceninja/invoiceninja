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
	 * @param  User $user
	 * @param  $ability
	 * @return bool
	 */
	public function before($user, $ability) : bool
	{
	    if ($user->isAdmin()) {
	        return true;
	    }
	}

	/**
	 *  Checks if the user has create permissions
	 * @param  User $user
	 * @param  $entity
	 * @return bool
	 */
	public function create(User $user, $entity) : bool
	{
		$entity = strtolower(class_basename($entity));

			return $user->hasPermission('create_' . $entity);
	}


	/**
	 *  Checks if the user has edit permissions
	 * @param  User $user
	 * @param  $entity
	 * @return bool
	 */
	public function edit(User $user, $entity) : bool
	{
		$entity = strtolower(class_basename($entity));

			return $user->hasPermission('edit_' . $entity) || $user->owns($entity);
	}


	/**
	 *  Checks if the user has view permissions
	 * @param  User $user
	 * @param  $entity
	 * @return bool
	 */
	public function view(User $user, $entity) : bool
	{
		$entity = strtolower(class_basename($entity));

			return $user->hasPermission('view_' . $entity) || $user->owns($entity);		
	}
}
