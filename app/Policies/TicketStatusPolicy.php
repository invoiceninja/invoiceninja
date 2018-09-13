<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class TicketStatusPolicy
 * @package App\Policies
 */
class TicketStatusPolicy extends EntityPolicy
{
    /**
     * @param User  $user
     *
     * @return bool
     */
    public function create(User $user)
    {
        if (! $this->createPermission($user, ENTITY_TICKET_STATUS))
            return false;


        return $user->hasFeature(FEATURE_TICKETS);
    }
}
