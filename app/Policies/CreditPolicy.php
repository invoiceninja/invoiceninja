<?php

namespace App\Policies;

use App\Models\User;


class CreditPolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_CREDIT);
    }
}
