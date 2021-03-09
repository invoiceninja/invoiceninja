<?php

namespace App\Http\Requests\BillingSubscription;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;

class UpdateBillingSubscriptionRequest extends Request
{
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('edit', $this->billing_subscription);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
