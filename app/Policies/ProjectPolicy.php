<?php

namespace App\Policies;

class ProjectPolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_PROJECT);
    }
}
