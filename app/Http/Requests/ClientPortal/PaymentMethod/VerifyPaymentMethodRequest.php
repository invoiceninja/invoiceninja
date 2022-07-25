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
            'transactions.*' => 'integer',
        ];
    }
}
