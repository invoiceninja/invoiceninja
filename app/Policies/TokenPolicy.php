<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class TokenPolicy
 * @package App\Policies
 */
class TokenPolicy extends EntityPolicy
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
