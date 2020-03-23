<?php

namespace App\Http\Requests\ClientPortal;

use Illuminate\Foundation\Http\FormRequest;

class ShowQuoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;

        // return auth()->user()->client->id === $this->quote->client_id;
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
