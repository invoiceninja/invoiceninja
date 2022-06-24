<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
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
            'product_ids' => ['sometimes'],
            'recurring_product_ids' => ['sometimes'],
            'assigned_user_id' => ['sometimes'],
            'is_recurring' => ['sometimes'],
            'frequency_id' => ['required_with:recurring_product_ids'],
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
            'refund_period' => ['sometimes'],
            'webhook_configuration' => ['array'],
            'name' => ['sometimes', Rule::unique('subscriptions')->where('company_id', auth()->user()->company()->id)->ignore($this->subscription->id)],
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
