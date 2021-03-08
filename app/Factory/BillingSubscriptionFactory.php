<?php


namespace App\Factory;


use App\Models\BillingSubscription;

class BillingSubscriptionFactory
{
    public static function create(int $company_id, int $user_id): BillingSubscription
    {
        $billing_subscription = new BillingSubscription();

        return $billing_subscription;
    }
}
