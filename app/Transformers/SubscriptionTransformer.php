<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\Subscription;
use App\Utils\Traits\MakesHash;

class SubscriptionTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
    ];

    public function transform(Subscription $subscription): array
    {
        $std = new \stdClass;

        return [
            'id' => $this->encodePrimaryKey($subscription->id),
            'user_id' => $this->encodePrimaryKey($subscription->user_id),
            'product_id' => $this->encodePrimaryKey($subscription->product_id),
            'assigned_user_id' => $this->encodePrimaryKey($subscription->assigned_user_id),
            'company_id' => $this->encodePrimaryKey($subscription->company_id),
            'is_recurring' => (bool)$subscription->is_recurring,
            'frequency_id' => (string)$subscription->frequency_id,
            'auto_bill' => (string)$subscription->auto_bill,
            'promo_code' => (string)$subscription->promo_code,
            'promo_discount' => (float)$subscription->promo_discount,
            'is_amount_discount' => (bool)$subscription->is_amount_discount,
            'allow_cancellation' => (bool)$subscription->allow_cancellation,
            'per_seat_enabled' => (bool)$subscription->per_set_enabled,
            'min_seats_limit' => (int)$subscription->min_seats_limit,
            'max_seats_limit' => (int)$subscription->max_seats_limit,
            'trial_enabled' => (bool)$subscription->trial_enabled,
            'trial_duration' => (int)$subscription->trial_duration,
            'allow_query_overrides' => (bool)$subscription->allow_query_overrides,
            'allow_plan_changes' => (bool)$subscription->allow_plan_changes,
            'plan_map' => (string)$subscription->plan_map,
            'refund_period' => (int)$subscription->refund_period,
            'webhook_configuration' => $subscription->webhook_configuration ?: $std,
            'purchase_page' => (string)route('client.subscription.purchase', $subscription->hashed_id),
            'is_deleted' => (bool)$subscription->is_deleted,
            'created_at' => (int)$subscription->created_at,
            'updated_at' => (int)$subscription->updated_at,
            'archived_at' => (int)$subscription->deleted_at,
        ];
    }

}
