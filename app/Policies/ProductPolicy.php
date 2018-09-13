<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class ProductPolicy.
 */
class ProductPolicy extends EntityPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_PRODUCT);
    }
}
