<?php

namespace App\Policies;

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
    public static function create(User $user, $item)
    {
        if (! parent::create($user, $item)) {
            return false;
        }

        return $user->hasFeature(FEATURE_TICKETS);
    }

    public static function view(User $user, $item, $entityType = null)
    {
        if(!$entityType)
            $entityType = is_string($item) ? $item : $item->getEntityType();

        return $user->hasPermission('view_' . $entityType) || $user->owns($item) || $user->id == $item->agent_id;
    }

    public static function viewModel(User $user, $model, $entityType = null)
    {
        if($model->user_id == $user->id)
            return true;
        elseif($model->agent_id == $user->id)
            return true;
        elseif($user->hasPermission('view_'.$entityType))
            return true;
        else
            return false;
    }

    public static function isMergeable(User $user, $item)
    {
        if($item->is_internal == false && $item->status_id != TICKET_STATUS_MERGED)
            return false;
        else
            return true;
    }

    public static function isTicketMaster(User $user, $item)
    {
        return $user->isTicketMaster() || $user->is_admin;
    }


}
