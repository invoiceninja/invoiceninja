<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class ProductPolicy
 */
class ProductPolicy extends EntityPolicy
{

    /**
     * @param User $user
     * @param $item
     * @return mixed
     */
	public static function edit(User $user, $item) {
        return $user->hasPermission('admin');
    }

    /**
     * @param User $user
     * @return mixed
     */
    public static function create(User $user) {
        return $user->hasPermission('admin');
    }
}