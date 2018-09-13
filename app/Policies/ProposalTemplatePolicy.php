<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class ProposalTemplatePolicy
 * @package App\Policies
 */
class ProposalTemplatePolicy extends EntityPolicy
{
    /**
     * @param User $user
     * @return bool
     */
    public function create(User $user)
    {
        return $this->createPermission($user, ENTITY_PROPOSAL);
    }
}
