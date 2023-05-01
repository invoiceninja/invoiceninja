<?php

/**
* Invoice Ninja (https://invoiceninja.com).
*
* @link https://github.com/invoiceninja/invoiceninja source repository
*
* @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
*
* @license https://www.elastic.co/licensing/elastic-license
*/

namespace App\Http\Requests\Shopify;

use Illuminate\Foundation\Http\FormRequest;

class CallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $signature = collect($this->toArray())
            ->except('hmac')
            ->toArray();

        $hmac = hash_hmac('sha256', http_build_query($signature), config('ninja.shopify.client_secret'));

        return $hmac === $this->hmac && $this->session()->get('shopify.nonce') === $this->state;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hmac' => ['required'],
            'state' => ['required'],
            'shop' => ['required', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com/'],
        ];
    }
}
