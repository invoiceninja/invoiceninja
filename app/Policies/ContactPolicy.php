<?php

namespace App\Policies;

class ContactPolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_CONTACT);
    }
}
