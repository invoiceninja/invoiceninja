<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Class InvoicePolicy
 * @package App\Policies
 */
class InvoicePolicy extends EntityPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_INVOICE);
    }

    /**
     * @param User $user
     * @param $item
     * @param null $entityType
     * @return bool
     */
    public function view(User $user, $item, $entityType = null)
    {
        $entityType = is_string($item) ? $item : $item->getEntityType();
            return $user->hasPermission('view_' . $entityType) || $user->owns($item);
    }


}
