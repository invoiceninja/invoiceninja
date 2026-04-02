<?php

namespace App\Http\Requests\ClientPortal\PaymentMethod;

use App\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentMethodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return (int) auth()->guard('contact')->user()->client_id === (int) $this->payment_method->client_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'transactions.*' => 'integer',
        ];
    }
}
