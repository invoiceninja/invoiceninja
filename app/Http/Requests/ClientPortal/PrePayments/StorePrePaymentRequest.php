<?php

namespace App\Http\Requests\ClientPortal\PrePayments;

use App\Utils\Number;
use App\Http\ViewComposers\PortalComposer;
use Illuminate\Foundation\Http\FormRequest;

class StorePrePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {

        auth()->guard('contact')->user()->loadMissing(['company']);

        auth()->guard('contact')->user()->loadMissing(['client' => function ($query) {
            $query->without('gateway_tokens', 'documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
        }]);

        return (bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_INVOICES);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'notes' => 'required|bail|',
            'amount' => 'required|bail|gte:minimum_amount|numeric',
            'minimum_amount' => '',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input['amount'] = Number::parseFloat($input['amount'], auth()->guard('contact')->user()->client->currency()->precision ?? 2);

        $this->replace($input);

    }
}
