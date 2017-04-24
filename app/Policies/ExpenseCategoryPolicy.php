<?php

namespace App\Policies;

use App\Models\User;

class ExpenseCategoryPolicy extends EntityPolicy
{
    /**
     * @param User  $user
     * @param mixed $item
     *
     * @return bool
     */
    public static function create(User $user, $item)
    {
        return $user->is_admin;
    }

    /**
     * @param User $user
     * @param $item
     *
     * @return bool
     */
    public static function edit(User $user, $item)
    {
        return $user->is_admin;
    }

    /**
     * @param User $user
     * @param $item
     *
     * @return bool
     */
    public static function view(User $user, $item)
    {
        return true;
    }

    /**
     * @param User $user
     * @param $ownerUserId
     *
     * @return bool
     */
    public static function viewByOwner(User $user, $ownerUserId)
    {
        return true;
    }

    /**
     * @param User $user
     * @param $ownerUserId
     *
     * @return bool
     */
    public static function editByOwner(User $user, $ownerUserId)
    {
        return $user->is_admin;
    }
}
