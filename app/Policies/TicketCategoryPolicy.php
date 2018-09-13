<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class TicketCategoryPolicy
 * @package App\Policies
 */
class TicketCategoryPolicy extends EntityPolicy
{
    /**
     * @param User  $user
     * @param mixed $item
     *
     * @return bool
     */
    public function create(User $user)
    {
        if (! $this->createPermission($user, ENTITY_TICKET_CATEGORY))
            return false;


        return $user->hasFeature(FEATURE_TICKETS);
    }
}
