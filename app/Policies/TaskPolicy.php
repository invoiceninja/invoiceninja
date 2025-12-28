<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Policies;

use App\Models\User;

/**
 * Class TaskPolicy.
 */
class TaskPolicy extends EntityPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('create_task') || $user->hasPermission('create_all');
    }
}
