<?php

namespace App\Policies;

/**
 * Class ProposalPolicy
 * @package App\Policies
 */
class ProposalPolicy extends EntityPolicy
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
