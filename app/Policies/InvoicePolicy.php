<?php

namespace App\Policies;

use App\Models\User;

class InvoicePolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_INVOICE);
    }

    public function view(User $user, $item, $entityType = null)
    {
        $entityType = is_string($item) ? $item : $item->getEntityType();
        return $user->hasPermission('view_' . $entityType) || $user->owns($item);
    }

    public function viewClient(User $user, $model, $entityType = null)
    {
        return $user->hasPermission('view_'.$entityType) || $user->id == $model->user_id;
    }
}
