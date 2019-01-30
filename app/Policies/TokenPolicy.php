<?php

namespace App\Policies;

use App\Models\User;

class TokenPolicy extends EntityPolicy
{
    public static function edit(User $user, $item)
    {
        return $user->hasPermission('admin');
    }

    public static function create(User $user, $item)
    {
        return $user->hasPermission('admin');
    }
}
