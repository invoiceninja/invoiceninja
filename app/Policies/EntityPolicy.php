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
     * @param $item - entity name or object
     *
     * @return bool
     */

    public function createPermission(User $user, $entityType)
    {

        if (! $this->checkModuleEnabled($user, $entityType))
            return false;

            return $user->hasPermission('create_' . $entityType);
    }

    /**
     * @param User $user
     * @param $item - entity name or object
     *
     * @return bool
     */

    public function edit(User $user, $item)
    {
        if (! $this->checkModuleEnabled($user, $item))
            return false;


        $entityType = is_string($item) ? $item : $item->getEntityType();
            return $user->hasPermission('edit_' . $entityType) || $user->owns($item);
    }

    /**
     * @param User $user
     * @param $item - entity name or object
     *
     * @return bool
     */

    public function view(User $user, $item, $entityType = null)
    {
        if (! $this->checkModuleEnabled($user, $item))
            return false;

        $entityType = is_string($item) ? $item : $item->getEntityType();
            return $user->hasPermission('view_' . $entityType) || $user->owns($item);
    }

    public function viewModel(User $user, $model)
    {
        if($user->hasPermission('view_'.$model->entityType))
            return true;
        elseif($model->user_id == $user->id)
            return true;
        elseif(isset($model->agent_id) && ($model->agent_id == $user->id))
            return true;
        else
            return false;
    }


    /**
     * @param User $user
     * @param $ownerUserId
     *
     * Legacy permissions - retaining these for legacy code however new code
     *                      should use auth()->user()->can('view', $ENTITY_TYPE)
     *
     * $ENTITY_TYPE can be either the constant ie ENTITY_INVOICE, or the entity $object
     *
     * @return bool
     */

    public function viewByOwner(User $user, $ownerUserId)
    {
        return $user->id == $ownerUserId;
    }

    /**
     * @param User $user
     * @param $ownerUserId
     *
     * Legacy permissions - retaining these for legacy code however new code
     *                      should use auth()->user()->can('edit', $ENTITY_TYPE)
     *
     * $ENTITY_TYPE can be either the constant ie ENTITY_INVOICE, or the entity $object
     *
     * @return bool
     */

    public function editByOwner(User $user, $ownerUserId)
    {
        return $user->id == $ownerUserId;
    }

    /**
     * @param User $user
     * @param $item - entity name or object
     * @return bool
     */

    public function checkModuleEnabled(User $user, $item)
    {
        $entityType = is_string($item) ? $item : $item->getEntityType();
            return $user->account->isModuleEnabled($entityType);
    }

    public function createEntity(User $user, $entityType)
    {
        // check if the feature is enabled
        if(! $user->hasFeature($entityType))
            return false;
        
        return $user->hasPermission('create_' . $entityType);
    }

}
