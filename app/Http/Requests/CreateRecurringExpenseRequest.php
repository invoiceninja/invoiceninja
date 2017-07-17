<?php

namespace App\Http\Requests;

class CreateRecurringExpenseRequest extends RecurringExpenseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', ENTITY_RECURRING_EXPENSE);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'amount' => 'numeric',
        ];
    }
}
