<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CreditPolicy extends EntityPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('create_credit') || $user->hasPermission('create_all');
    }
}
