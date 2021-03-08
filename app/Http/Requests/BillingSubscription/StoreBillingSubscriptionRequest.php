<?php

namespace App\Http\Requests\BillingSubscription;

use App\Http\Requests\Request;

class StoreBillingSubscriptionRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // TODO
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user_id' => ['sometimes'],
            'product_id' => ['sometimes'],
            'assigned_user_id' => ['sometimes'],
            'company_id' => ['sometimes'],
            'is_recurring' => ['sometimes'],
            'frequency_id' => ['sometimes'],
            'auto_bill' => ['sometimes'],
            'promo_code' => ['sometimes'],
            'promo_discount' => ['sometimes'],
            'is_amount_discount' => ['sometimes'],
            'allow_cancellation' => ['sometimes'],
            'per_set_enabled' => ['sometimes'],
            'min_seats_limit' => ['sometimes'],
            'max_seats_limit' => ['sometimes'],
            'trial_enabled' => ['sometimes'],
            'trial_duration' => ['sometimes'],
            'allow_query_overrides' => ['sometimes'],
            'allow_plan_changes' => ['sometimes'],
            'plan_map' => ['sometimes'],
            'refund_period' => ['sometimes'],
            'webhook_configuration' => ['sometimes'],
        ];
    }
}
