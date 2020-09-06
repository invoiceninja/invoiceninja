<?php

namespace App\Http\Requests\ClientPortal\Payments;

use App\Models\PaymentHash;
use Illuminate\Foundation\Http\FormRequest;

class PaymentResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'company_gateway_id' => 'required',
            'payment_hash' => 'required',
        ];
    }

    public function getPaymentHash()
    {
        $input = $this->all();

        return PaymentHash::whereRaw('BINARY `hash`= ?', [$input['payment_hash']])->first();
    }
}
