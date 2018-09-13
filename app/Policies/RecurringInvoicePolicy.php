<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class RecurringInvoicePolicy
 * @package App\Policies
 */
class RecurringInvoicePolicy extends EntityPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_INVOICE);
    }
}
