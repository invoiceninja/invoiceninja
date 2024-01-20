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
 * Class ClientPolicy.
 */
class ClientPolicy extends EntityPolicy
{
    /**
     *  Checks if the user has create permissions.
     *
     * @param  User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('create_client') || $user->hasPermission('create_all');
    }
}
