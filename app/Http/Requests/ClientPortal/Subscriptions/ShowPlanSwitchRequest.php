<?php

namespace App\Http\Requests\ClientPortal\Subscriptions;

use App\Models\Subscription;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class ShowPlanSwitchRequest extends FormRequest
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (bool) $this->recurring_invoice->subscription->allow_plan_changes;
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
