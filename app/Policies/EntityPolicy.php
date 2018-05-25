<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

/**
 * Class EntityPolicy.
 */
class EntityPolicy
{
    use HandlesAuthorization;

    /**
     * @param User  $user
     * @param mixed $item
     *
     * @return bool
     */
    public static function create(User $user, $item)
    {
        if (! static::checkModuleEnabled($user, $item)) {
            return false;
        }

        $entityType = is_string($item) ? $item : $item->getEntityType();

            return $user->hasPermission('create_' . $entityType);
    }

    /**
     * @param User $user
     * @param $item
     *
     * @return bool
     */
    public static function edit(User $user, $item)
    {
        if (! static::checkModuleEnabled($user, $item)) {
            return false;
        }

        $entityType = is_string($item) ? $item : $item->getEntityType();
            return $user->hasPermission('edit_' . $entityType) || $user->owns($item);
    }

    /**
     * @param User $user
     * @param $item
     *
     * @return bool
     */
    public static function view(User $user, $item)
    {
        if (! static::checkModuleEnabled($user, $item)) {
            return false;
        }

        $entityType = is_string($item) ? $item : $item->getEntityType();
            return $user->hasPermission('view_' . $entityType) || $user->owns($item);
    }

    /**
     * @param User $user
     * @param $ownerUserId
     *
     * @return bool
     */
    public static function viewByOwner(User $user, $ownerUserId)
    {
        return $user->id == $ownerUserId;
    }

    /**
     * @param User $user
     * @param $ownerUserId
     *
     * @return bool
     */
    public static function editByOwner(User $user, $ownerUserId)
    {
        return $user->id == $ownerUserId;
    }

    private static function checkModuleEnabled(User $user, $item)
    {
        $entityType = is_string($item) ? $item : $item->getEntityType();
        
        return $user->account->isModuleEnabled($entityType);
    }
}
