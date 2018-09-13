<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class VendorPolicy
 * @package App\Policies
 */
class VendorPolicy extends EntityPolicy
{
    /**
     * @param User  $user
     * @param mixed $item
     *
     * @return bool
     */
    public function create(User $user)
    {
        if (! $this->createPermission($user, ENTITY_VENDOR))
            return false;


        return $user->hasFeature(FEATURE_EXPENSES);
    }
}
