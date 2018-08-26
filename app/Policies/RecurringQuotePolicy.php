<?php

namespace App\Policies;

class RecurringQuotePolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_QUOTE);
    }
}
