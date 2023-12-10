<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Subscription;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends Request
{
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit', $this->subscription);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'name' => ['bail','sometimes', Rule::unique('subscriptions')->where('company_id', auth()->user()->company()->id)->ignore($this->subscription->id)],
            'group_id' => ['bail','sometimes', 'nullable', Rule::exists('group_settings', 'id')->where('company_id', auth()->user()->company()->id)],
            'assigned_user_id' => ['bail','sometimes', 'nullable', Rule::exists('users', 'id')->where('account_id', auth()->user()->account_id)],
            'product_ids' => 'bail|sometimes|nullable|string',
            'recurring_product_ids' => 'bail|sometimes|nullable|string',
            'is_recurring' => 'bail|sometimes|bool',
            'frequency_id' => 'bail|required_with:recurring_product_ids',
            'auto_bill' => 'bail|sometimes|nullable|string',
            'promo_code' => 'bail|sometimes|nullable|string',
            'promo_discount' => 'bail|sometimes|numeric',
            'is_amount_discount' => 'bail|sometimes|bool',
            'allow_cancellation' => 'bail|sometimes|bool',
            'per_set_enabled' => 'bail|sometimes|bool',
            'min_seats_limit' => 'bail|sometimes|numeric',
            'max_seats_limit' => 'bail|sometimes|numeric',
            'trial_enabled' => 'bail|sometimes|bool',
            'trial_duration' => 'bail|sometimes|numeric',
            'allow_query_overrides' => 'bail|sometimes|bool',
            'allow_plan_changes' => 'bail|sometimes|bool',
            'refund_period' => 'bail|sometimes|numeric',
            'webhook_configuration' => 'bail|array',
            'webhook_configuration.post_purchase_url' => 'bail|sometimes|nullable|string',
            'webhook_configuration.post_purchase_rest_method' => 'bail|sometimes|nullable|string',
            'webhook_configuration.post_purchase_headers' => 'bail|sometimes|array',
            'registration_required' => 'bail|sometimes|bool',
            'optional_recurring_product_ids' => 'bail|sometimes|nullable|string',
            'optional_product_ids' => 'bail|sometimes|nullable|string',
            'use_inventory_management' => 'bail|sometimes|bool',
        ];

        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }
}
