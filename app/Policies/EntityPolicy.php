<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Policies;

use App\Models\User;

/**
 * Class EntityPolicy.
 */
class EntityPolicy
{
    /**
     * Fires before any of the custom policy methods.
     *
     * Only fires if true, if false we continue.....
     *
     * Do not use this function!!!! We MUST also check company_id,
     *
     * @param User $user
     * @param  $ability
     * @return void /void
     */
    public function before($user, $ability)
    {
    }

    /**
     * Checks if the user has edit permissions.
     *
     * We MUST also check that the user can both edit a entity and also check the entity belongs to the users company!!!!!!
     *
     * @param  User $user
     * @param  $entity
     * @return bool
     */
    public function edit(User $user, $entity): bool
    {
        return ($user->isAdmin() && $entity->company_id == $user->companyId())
            || ($user->hasPermission('edit_'.\Illuminate\Support\Str::snake(class_basename($entity))) && $entity->company_id == $user->companyId())
            // || ($user->hasPermission('edit_all') && $entity->company_id == $user->companyId()) //this is redundant as the edit_ check covers the _all check
            || ($user->owns($entity) && $entity->company_id == $user->companyId())
            || ($user->assigned($entity) && $entity->company_id == $user->companyId());
    }

    /**
     *  Checks if the user has view permissions.
     *
     * We MUST also check that the user can both view a entity and also check the entity belongs to the users company!!!!!!
     * @param  User $user
     * @param  $entity
     * @return bool
     */
    public function view(User $user, $entity): bool
    {
        return ($user->isAdmin() && $entity->company_id == $user->companyId())
            || ($user->hasPermission('view_'.\Illuminate\Support\Str::snake(class_basename($entity))) && $entity->company_id == $user->companyId())
            // || ($user->hasPermission('view_all') && $entity->company_id == $user->companyId()) //this is redundant as the edit_ check covers the _all check
            || ($user->owns($entity) && $entity->company_id == $user->companyId())
            || ($user->assigned($entity) && $entity->company_id == $user->companyId());
    }
}
