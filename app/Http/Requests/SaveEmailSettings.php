<?php

namespace App\Http\Requests;

class SaveEmailSettings extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->is_admin && $this->user()->isPro();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'bcc_email' => 'email',
            'reply_to_email' => 'email',
        ];
    }
}
