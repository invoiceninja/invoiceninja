<?php

namespace App\Ninja\Repositories;

use App\Models\Company;
use App\Models\DbServer;

class ReferralRepository
{
    public function getCounts($referralCode)
    {
        $counts = [
            'free' => 0,
            'pro' => 0,
            'enterprise' => 0,
        ];

        if (! $referralCode) {
            return $counts;
        }

        $current = config('database.default');
        $databases = env('MULTI_DB_ENABLED') ? DbServer::all()->pluck('name')->toArray() : [$current];

        foreach ($databases as $database) {
            config(['database.default' => $database]);
            $accounts = Company::whereReferralCode($referralCode)->get();

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
        }

        config(['database.default' => $current]);

        return $counts;
    }
}
