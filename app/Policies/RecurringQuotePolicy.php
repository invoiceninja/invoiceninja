<?php

namespace App\Policies;
use App\Models\User;

/**
 * Class RecurringQuotePolicy
 * @package App\Policies
 */
class RecurringQuotePolicy extends EntityPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_QUOTE);
    }

}
