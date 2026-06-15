<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Livewire\BillingPortal\Purchase;
use App\Models\Subscription;
use App\Services\Subscription\StepService;

class SubscriptionFactory
{
    public static function create(int $company_id, int $user_id): Subscription
    {
        $billing_subscription = new Subscription();
        $billing_subscription->company_id = $company_id;
        $billing_subscription->user_id = $user_id;
        $billing_subscription->steps = collect(Purchase::defaultSteps())
            ->map(fn($step) => StepService::mapClassNameToString($step))
            ->implode(',');

        $billing_subscription->webhook_configuration = [
            'post_purchase_url' => '',
            'post_purchase_rest_method' => 'post',
            'post_purchase_headers' => [],
            'return_url' => '',
        ];
        
        return $billing_subscription;
    }
}
