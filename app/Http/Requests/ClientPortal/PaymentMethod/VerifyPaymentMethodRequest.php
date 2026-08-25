<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\ClientPortal\PaymentMethod;

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
    public function rules(): array
    {
        if (! $this->isMethod('POST')) {
            return [];
        }

        $state = $this->payment_method->meta->state ?? null;

        if ($state === 'inactive') {
            return [
                'setup_intent_id' => ['required', 'string', 'starts_with:seti_'],
            ];
        }

        if ($state === 'authorized') {
            return [];
        }

        return [
            'transactions.*' => ['integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'setup_intent_id.required' => ctrans('texts.unable_to_verify_payment_method'),
            'setup_intent_id.starts_with' => ctrans('texts.unable_to_verify_payment_method'),
        ];
    }
}
