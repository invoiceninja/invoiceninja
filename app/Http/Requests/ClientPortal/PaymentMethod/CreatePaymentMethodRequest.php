<?php

namespace App\Http\Requests\ClientPortal\PaymentMethod;

use App\Http\Requests\Request;
use App\Models\Client;
use function auth;
use function collect;
use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentMethodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var Client $client */
        $client = auth()->guard('contact')->user()->client;

        $available_methods = [];

        collect($client->service()->getPaymentMethods(-1))
            ->filter(function ($method) use (&$available_methods) {
                $available_methods[] = $method['gateway_type_id'];
            });

        if (in_array($this->query('method'), $available_methods)) {
            return true;
        }

        return false;
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
