<?php

namespace App\Http\Requests\BillingSubscription;

use App\Http\Requests\Request;

class CreateBillingSubscriptionRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
//        return auth()->user()->can('create', BillingSubscription::class); // TODO
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
