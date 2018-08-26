<?php

namespace App\Policies;

use App\Models\User;

class QuotePolicy extends EntityPolicy
{
    /**
     * @param User  $user
     * @param mixed $item
     *
     * @return bool
     */
    public function create(User $user)
    {
        if(!$this->createPermission($user, ENTITY_QUOTE))
            return false;

        return $user->hasFeature(FEATURE_QUOTES);
    }
}
