<?php

namespace App\Policies;

use App\Models\User;

class RecurringExpensePolicy extends EntityPolicy
{
    /**
     * @param User  $user
     * @param mixed $item
     *
     * @return bool
     */
    public function create(User $user)
    {

        if (! $this->createPermission($user, ENTITY_EXPENSE))
            return false;


        return $user->hasFeature(FEATURE_EXPENSES);
    }
}


