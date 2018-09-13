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
    public  function create(User $user)
    {
        return $user->is_admin;
    }

    /**
     * @param User $user
     * @param $item
     *
     * @return bool
     */
    public  function edit(User $user, $item)
    {
        return $user->is_admin;
    }

    /**
     * @param User $user
     * @param $item
     *
     * @return bool
     */
    public  function view(User $user, $item, $entityType = null)
    {
        return true;
    }

    /**
     * @param User $user
     * @param $ownerUserId
     *
     * @return bool
     */
    public  function viewByOwner(User $user, $ownerUserId)
    {
        return true;
    }

    /**
     * @param User $user
     * @param $ownerUserId
     *
     * @return bool
     */
    public  function editByOwner(User $user, $ownerUserId)
    {
        return $user->is_admin;
    }
}
