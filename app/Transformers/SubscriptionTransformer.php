<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
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
    protected array $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
    ];

    public function transform(Subscription $subscription): array
    {
        $company = $subscription->company;

        return [
            'id' => $this->encodePrimaryKey($subscription->id),
            'user_id' => $this->encodePrimaryKey($subscription->user_id),
            'group_id' => $this->encodePrimaryKey($subscription->group_id),
            'product_ids' => (string) $subscription->product_ids,
            'name' => (string) $subscription->name,
            'recurring_product_ids' => (string) $subscription->recurring_product_ids,
            'assigned_user_id' => $this->encodePrimaryKey($subscription->assigned_user_id),
            'company_id' => $this->encodePrimaryKey($subscription->company_id),
            'price' => (float) $subscription->price,
            'promo_price' => (float) $subscription->promo_price,
            'frequency_id' => (string) $subscription->frequency_id,
            'auto_bill' => (string) $subscription->auto_bill,
            'promo_code' => (string) $subscription->promo_code,
            'promo_discount' => (float) $subscription->promo_discount,
            'is_amount_discount' => (bool) $subscription->is_amount_discount,
            'allow_cancellation' => (bool) $subscription->allow_cancellation,
            'per_seat_enabled' => (bool) $subscription->per_seat_enabled,
            'max_seats_limit' => (int) $subscription->max_seats_limit,
            'trial_enabled' => (bool) $subscription->trial_enabled,
            'trial_duration' => (int) $subscription->trial_duration,
            'allow_query_overrides' => (bool) $subscription->allow_query_overrides,
            'allow_plan_changes' => (bool) $subscription->allow_plan_changes,
            'refund_period' => (int) $subscription->refund_period,
            'webhook_configuration' => $subscription->webhook_configuration ?: [],
            'purchase_page' => (string) $company->domain()."/client/subscriptions/{$subscription->hashed_id}/purchase",
            //'purchase_page' => (string)route('client.subscription.purchase', $subscription->hashed_id),
            'currency_id' => (string) $subscription->currency_id,
            'is_deleted' => (bool) $subscription->is_deleted,
            'created_at' => (int) $subscription->created_at,
            'updated_at' => (int) $subscription->updated_at,
            'archived_at' => (int) $subscription->deleted_at,
            'plan_map' => '', //@deprecated 03/04/2021
            'use_inventory_management' => (bool) $subscription->use_inventory_management,
            'optional_recurring_product_ids' => (string)$subscription->optional_recurring_product_ids,
            'optional_product_ids' => (string) $subscription->optional_product_ids,
            'registration_required' => (bool) $subscription->registration_required,
            'steps' => $subscription->steps,
            'remaining_cycles' => (int) $subscription->remaining_cycles,
        ];
    }
}
