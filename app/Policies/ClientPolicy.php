<?php

namespace App\Policies;

use App\Models\User;

class ClientPolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_CLIENT);
    }
}
