<?php

namespace App\Policies;

/**
 * Class ProposalCategoryPolicy
 * @package App\Policies
 */
class ProposalCategoryPolicy extends EntityPolicy
{
    /**
     * @param User $user
     * @return mixed
     */
    public function create(User $user)
    {
        return $user->is_admin;
    }
}
