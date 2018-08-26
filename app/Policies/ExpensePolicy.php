<?php

namespace App\Policies;

use App\Models\User;

class ExpensePolicy extends EntityPolicy
{
    /**
     * @param User  $user
     *
     * @return bool
     */
    public function create(User $user)
    {
        if(!$this->createPermission($user, ENTITY_USER))
            return false;

        return $user->hasFeature(FEATURE_EXPENSES);
    }
}
