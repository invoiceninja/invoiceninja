<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class ExpensePolicy
 */
class ExpensePolicy extends EntityPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public static function create(User $user) {
        if ( ! parent::create($user)) {
            return false;
        }

        return $user->hasFeature(FEATURE_EXPENSES);
    }

}
