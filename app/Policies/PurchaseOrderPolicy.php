<?php


namespace App\Policies;


use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PurchaseOrderPolicy extends EntityPolicy
{
    use HandlesAuthorization;

    public function create(User $user) : bool
    {
        return $user->isAdmin() || $user->hasPermission('create_purchase_order') || $user->hasPermission('create_all');
    }

}
