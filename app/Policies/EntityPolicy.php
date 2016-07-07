<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class EntityPolicy
 */
class EntityPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     * @return bool
     */
    public static function create(User $user) {
        return $user->hasPermission('create_all');
    }

    /**
     * @param User $user
     * @param $item
     *
     * @return bool
     */
    public static function edit(User $user, $item) {
        return $user->hasPermission('edit_all') || $user->owns($item);
    }

    /**
     * @param User $user
     * @param $item
     *
     * @return bool
     */
    public static function view(User $user, $item) {
        return $user->hasPermission('view_all') || $user->owns($item);
    }

    /**
     * @param User $user
     * @param $ownerUserId
     * @return bool
     */
    public static function viewByOwner(User$user, $ownerUserId) {
        return $user->hasPermission('view_all') || $user->id == $ownerUserId;
    }

    /**
     * @param User $user
     * @param $ownerUserId
     * @return bool
     */
    public static function editByOwner(User $user, $ownerUserId) {
        return $user->hasPermission('edit_all') || $user->id == $ownerUserId;
    }
}
