<?php

namespace App\Policies;

class ProposalCategoryPolicy extends EntityPolicy
{
    public function create(User $user)
    {
        return $user->is_admin;
    }
}
