<?php

namespace App\Http\Requests;

use Auth;

class UpdateUserRequest extends EntityRequest
{
    // Expenses

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::user()->is_admin || $this->user()->id == Auth::user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => 'email|required|unique:users,email,' . Auth::user()->id . ',id',
            'first_name' => 'required',
            'last_name' => 'required',
        ];
    }
}
