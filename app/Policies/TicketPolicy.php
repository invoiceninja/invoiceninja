<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TicketPolicy extends EntityPolicy
{
    /**
     * @param User  $user
     * @param mixed $item
     *
     * @return bool
     */
    public function create(User $user)
    {
        if (! $this->createPermission($user, ENTITY_TICKET))
            return false;

        return $user->hasFeature(FEATURE_TICKETS);
    }

    public function view(User $user, $item, $entityType = null)
    {
        if(!$entityType)
            $entityType = is_string($item) ? $item : $item->getEntityType();

        return $user->hasPermission('view_' . $entityType) || $user->owns($item) || $user->id == $item->agent_id;
    }


    public function isMergeable(User $user, $item)
    {
        if($item->is_internal == false && $item->status_id != TICKET_STATUS_MERGED)
            return false;
        else
            return true;
    }

    public function isTicketMaster(User $user, $item)
    {
        return $user->isTicketMaster() || $user->is_admin;
    }


}
