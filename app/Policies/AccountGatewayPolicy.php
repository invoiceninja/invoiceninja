<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class AccountGatewayPolicy.
 */
class AccountGatewayPolicy extends EntityPolicy
{
    /**
     * @param User $user
     * @param $item
     *
     * @return bool
     */
    public static function edit(User $user, $item)
    {
        return $user->hasPermission('admin');
    }

    /**
     * @param User  $user
     * @param mixed $item
     *
     * @return bool
     */
    public static function create(User $user, $item)
    {
        return $user->hasPermission('admin');
    }
}
