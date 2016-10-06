<?php namespace App\Ninja\Repositories;

use App\Models\Account;

class ReferralRepository
{
    public function getCounts($userId)
    {
        $accounts = Account::where('referral_user_id', $userId)->get();

        $counts = [
            'free' => 0,
            'pro' => 0,
            'enterprise' => 0
        ];

        foreach ($accounts as $account) {
            $counts['free']++;
            $plan = $account->getPlanDetails(false, false);

            if ($plan) {
                $counts['pro']++;
                if ($plan['plan'] == PLAN_ENTERPRISE) {
                    $counts['enterprise']++;
                }
            }
        }

        return $counts;
    }
}
