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

    public function edit(User $user, $entity)
    {

        return $user->owns($entity) || $user->hasPermission('edit_'. ENTITY_PROPOSAL);

    }
}
