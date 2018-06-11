<?php

namespace App\Policies;

use App\Models\User;

class TaskPolicy extends EntityPolicy
{
    /**
     * @param User  $user
     * @param mixed $item
     *
     * @return bool
     */
    public static function create(User $user, $item)
    {

        if ( $user->hasPermission('manage_own_tasks') )
            return true;

        if (! parent::create($user, $item)) {
            return false;
        }

        return $user->hasFeature(FEATURE_TASKS);
    }
}
