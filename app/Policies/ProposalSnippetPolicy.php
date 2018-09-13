<?php

namespace App\Policies;

use App\Models\User;


/**
 * Class ProposalSnippetPolicy
 * @package App\Policies
 */
class ProposalSnippetPolicy extends EntityPolicy
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
