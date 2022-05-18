<?php


namespace App\Http\Requests\TaskScheduler;


use App\Http\Requests\Request;

class CreateScheduledTaskRequest extends Request
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

    public function rules()
    {
        return [
            'paused' => 'sometimes|bool',
            'archived' => 'sometimes|bool',
            'repeat_every' => 'required|string|in:DAY,WEEK,MONTH,3MONTHS,YEAR',
            'start_from' => 'sometimes|string',
            'job' => 'required',

        ];
    }
}
