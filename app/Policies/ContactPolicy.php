<?php

namespace App\Policies;

use App\Models\User;


class ContactPolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_CONTACT);
    }
}
