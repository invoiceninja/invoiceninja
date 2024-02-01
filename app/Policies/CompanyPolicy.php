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

use App\Models\Company;
use App\Models\User;

/**
 * Class CompanyPolicy.
 */
class CompanyPolicy extends EntityPolicy
{
    /**
     *  Checks if the user has create permissions.
     *
     * @param  User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('create_company') || $user->hasPermission('create_all');
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
        return ($user->isAdmin() && $entity->id == $user->companyId())
            || ($user->hasPermission('view_'.strtolower(class_basename($entity))) && $entity->id == $user->companyId())
            // || ($user->hasPermission('view_all') && $entity->id == $user->companyId())
            || $user->owns($entity)
            || $user->companyId() == $entity->id;
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
        return ($user->isAdmin() && $entity->id == $user->companyId())
            || ($user->hasPermission('edit_'.strtolower(class_basename($entity))) && $entity->id == $user->companyId())
            // || ($user->hasPermission('edit_all') && $entity->id == $user->companyId())
            || $user->owns($entity);
    }
}
