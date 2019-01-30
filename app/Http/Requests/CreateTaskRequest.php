<?php

namespace App\Http\Requests;

class CreateTaskRequest extends TaskRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', ENTITY_TASK);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'time_log' => 'time_log',
        ];
    }
}
