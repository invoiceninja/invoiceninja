<?php

namespace App\Ninja\Repositories;

use App\Models\Account;

class NinjaRepository
{
    public function updatePlanDetails($clientPublicId, $data)
    {
        $account = Account::whereId($clientPublicId)->first();

        if (! $account) {
            return;
        }

        $company = $account->company;
        $company->fill($data);
        $company->save();
    }
}
