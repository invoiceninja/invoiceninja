<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\ClientPortal\Subscriptions;

use App\Exceptions\Ninja\ClientPortalAuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class ShowPlanSwitchRequest extends FormRequest
{
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

    protected function failedAuthorization()
    {
        throw new ClientPortalAuthorizationException('Unable to change plans due to a restriction on this product.', 400);
    }
}
