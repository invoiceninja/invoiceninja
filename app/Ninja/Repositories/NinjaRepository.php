<?php namespace App\Ninja\Repositories;

use App\Models\Account;

class NinjaRepository
{
    public function updateProPlanPaid($clientPublicId, $proPlanPaid)
    {
        $account = Account::whereId($clientPublicId)->first();

        if (!$account) {
            return;
        }

        $account->pro_plan_paid = $proPlanPaid;
        $account->save();
    }
}
