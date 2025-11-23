<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Twilio;

use App\Http\Requests\Request;

class GenerateSmsRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }


    public function rules()
    {
        return [
            'phone' => 'required|regex:^\+[1-9]\d{1,14}$^',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator)
    {
        $user = auth()->user();

        $key = "phone_verification_code_{$user->id}_{$user->account_id}";
        $count = \Illuminate\Support\Facades\Cache::get($key);

        if($count && $count > 1) {

            \Illuminate\Support\Facades\Cache::put($key, $count + 1, 300);
            $validator->after(function ($validator) {
                $validator->errors()->add('phone', 'You requested a verification code recently. Please retry again in a few minutes.');
            });
            
        }
        else{
            \Illuminate\Support\Facades\Cache::put($key, 1, 300);
        }

    }
}
