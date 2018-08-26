<?php

namespace App\Policies;

/**
 * Class ProductPolicy.
 */
class ProductPolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_PRODUCT);
    }
}
