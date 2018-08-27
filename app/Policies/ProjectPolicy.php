<?php

namespace App\Policies;

use App\Models\User;


/**
 * Class ProjectPolicy
 * @package App\Policies
 */
class ProjectPolicy extends EntityPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_PROJECT);
    }
}
