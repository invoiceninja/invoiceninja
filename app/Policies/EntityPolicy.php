<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

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

        return $user->hasPermission('create_' . $item);
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

        return $user->hasPermission('edit_' . $item) || $user->owns($item);
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

        return $user->hasPermission('view_' . $item) || $user->owns($item);
    }

    /**
     * @param User $user
     * @param $ownerUserId
     *
     * @return bool
     */
    public static function viewByOwner(User $user, $ownerUserId)
    {
        //todo permissions
        //return $user->hasPermission('view') || $user->id == $ownerUserId;
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
        //todo permissions
        //return $user->hasPermission('edit') || $user->id == $ownerUserId;
        return $user->id == $ownerUserId;
    }

    private static function checkModuleEnabled(User $user, $item)
    {
        $entityType = is_string($item) ? $item : $item->getEntityType();
        
        return $user->account->isModuleEnabled($entityType);
    }
}
