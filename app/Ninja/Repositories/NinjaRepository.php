<?php namespace App\Ninja\Repositories;

use App\Models\Account;

class NinjaRepository
{
    public function updatePlanDetails($clientPublicId, $data)
    {
        $account = Account::whereId($clientPublicId)->first();

        if (!$account) {
            return;
        }

        $company = $account->company;
        $company->plan = !empty($data['plan']) && $data['plan'] != PLAN_FREE?$data['plan']:null;
        $company->plan_term = !empty($data['plan_term'])?$data['plan_term']:null;
        $company->plan_paid = !empty($data['plan_paid'])?$data['plan_paid']:null;
        $company->plan_started = !empty($data['plan_started'])?$data['plan_started']:null;
        $company->plan_expires = !empty($data['plan_expires'])?$data['plan_expires']:null;
                
        $company->save();
    }
}
