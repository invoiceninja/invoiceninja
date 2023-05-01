<?php

namespace App\Http\Requests\Shopify;

use Illuminate\Foundation\Http\FormRequest;

class InitialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $signature = collect($this->toArray())
            ->except('hmac')
            ->toArray();

        $hmac = hash_hmac('sha256', http_build_query($signature), config('ninja.shopify.client_secret'));

        return $hmac === $this->hmac;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hmac' => ['required'],
        ];
    }
}
