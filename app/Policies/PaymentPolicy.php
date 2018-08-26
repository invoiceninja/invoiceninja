<?php

namespace App\Policies;

class PaymentPolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_PAYMENT);
    }
}
