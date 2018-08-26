<?php

namespace App\Policies;

use App\Models\User;

class SubscriptionPolicy extends EntityPolicy
{
    public function edit(User $user, $item)
    {
        return $user->hasPermission('admin');
    }

    public function create(User $user)
    {
        return $user->hasPermission('admin');
    }
}
