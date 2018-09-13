<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class RecurringExpensePolicy
 * @package App\Policies
 */
class RecurringExpensePolicy extends EntityPolicy
{
    /**
     * @param User  $user
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


