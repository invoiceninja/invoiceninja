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


use App\Models\BillingSubscription;
use App\Utils\Traits\MakesHash;

class BillingSubscriptionTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected $defaultIncludes = [];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'product',
    ];

    public function transform(BillingSubscription $billing_subscription): array
    {
        $std = new stdClass;

        return [
            'id' => $this->encodePrimaryKey($billing_subscription->id),
            'user_id' => $this->encodePrimaryKey($billing_subscription->user_id),
            'product_id' => $this->encodePrimaryKey($billing_subscription->product_id),
            'assigned_user_id' => $this->encodePrimaryKey($billing_subscription->assigned_user_id),
            'company_id' => $this->encodePrimaryKey($billing_subscription->company_id),
            'is_recurring' => (bool)$billing_subscription->is_recurring,
            'frequency_id' => (string)$billing_subscription->frequency_id,
            'auto_bill' => (string)$billing_subscription->auto_bill,
            'promo_code' => (string)$billing_subscription->promo_code,
            'promo_discount' => (float)$billing_subscription->promo_discount,
            'is_amount_discount' => (bool)$billing_subscription->is_amount_discount,
            'allow_cancellation' => (bool)$billing_subscription->allow_cancellation,
            'per_seat_enabled' => (bool)$billing_subscription->per_set_enabled,
            'min_seats_limit' => (int)$billing_subscription->min_seats_limit,
            'max_seats_limit' => (int)$billing_subscription->max_seats_limit,
            'trial_enabled' => (bool)$billing_subscription->trial_enabled,
            'trial_duration' => (int)$billing_subscription->trial_duration,
            'allow_query_overrides' => (bool)$billing_subscription->allow_query_overrides,
            'allow_plan_changes' => (bool)$billing_subscription->allow_plan_changes,
            'plan_map' => (string)$billing_subscription->plan_map,
            'refund_period' => (int)$billing_subscription->refund_period,
            'webhook_configuration' => $billing_subscription->webhook_configuration ?: $std,
            'purchase_page' => (string)route('client.subscription.purchase', $billing_subscription->hashed_id),
            'is_deleted' => (bool)$billing_subscription->is_deleted,
            'created_at' => (int)$billing_subscription->created_at,
            'updated_at' => (int)$billing_subscription->updated_at,
            'archived_at' => (int)$billing_subscription->deleted_at,
        ];
    }

    public function includeProduct(BillingSubscription $billing_subscription): \League\Fractal\Resource\Item
    {
        $transformer = new ProductTransformer($this->serializer);

        return $this->includeItem($billing_subscription->product, $transformer, Product::class);
    }
}
