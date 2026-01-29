<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ChartOfAccount;

class ChartOfAccountPolicy
{
    /**
     * List accounts
     */
    public function viewAny(User $user)
    {
        return $user->isAdmin() || $user->hasPermission('view_reports');
    }

    /**
     * Create account
     */
    public function create(User $user)
    {
        return $user->isAdmin();
    }

    /**
     * Update account
     */
    public function update(User $user, ChartOfAccount $account)
    {
        return $account->company_id === $user->companyId()
            && $user->isAdmin();
    }

    /**
     * Disable account
     */
    public function delete(User $user, ChartOfAccount $account)
    {
        return $account->company_id === $user->companyId()
            && $user->isAdmin();
    }
}
