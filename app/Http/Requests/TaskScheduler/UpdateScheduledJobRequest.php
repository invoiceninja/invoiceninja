<?php

namespace App\Http\Requests\TaskScheduler;

use App\Http\Requests\Request;

class UpdateScheduledJobRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'action_name' => 'sometimes|string',
        ];
    }
}
