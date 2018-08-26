<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class SubscriptionPolicy
 * @package App\Policies
 */
class SubscriptionPolicy extends EntityPolicy
{
    /**
     * @param User $user
     * @param $item
     * @return bool
     */
    public function edit(User $user, $item)
    {
        return $user->hasPermission('admin');
    }

    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user)
    {
        return $user->hasPermission('admin');
    }
}
