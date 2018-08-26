<?php

namespace App\Policies;

class CreditPolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_CREDIT);
    }
}
