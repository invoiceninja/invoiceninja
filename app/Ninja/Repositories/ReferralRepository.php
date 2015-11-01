<?php namespace App\Ninja\Repositories;

use DB;
use Utils;

class ReferralRepository
{
    public function getCounts($userId)
    {
        $accounts = DB::table('accounts')
                        ->where('referral_user_id', $userId)
                        ->get(['id', 'pro_plan_paid']);

        $counts = [
            'free' => 0,
            'pro' => 0
        ];

        foreach ($accounts as $account) {
            $counts['free']++;
            if (Utils::withinPastYear($account->pro_plan_paid)) {
                $counts['pro']++;
            }
        }

        return $counts;
    }



}