<?php

namespace App\Policies;

class RecurringInvoicePolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_INVOICE);
    }
}
