<?php

namespace App\Http\Requests\ClientPortal\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class ShowTasksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (bool)auth()->user('contact')->client->getSetting('enable_client_portal_tasks');
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
