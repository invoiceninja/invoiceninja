<?php

namespace App\Http\Requests\CalendarConnection;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendarConnectionCalendarsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'calendar_ids' => ['required', 'array', 'min:1'],
            'calendar_ids.*' => ['required', 'string', 'distinct'],
        ];
    }
}
