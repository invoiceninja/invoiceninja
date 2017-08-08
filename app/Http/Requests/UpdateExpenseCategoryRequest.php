<?php

namespace App\Http\Requests;

class UpdateExpenseCategoryRequest extends ExpenseCategoryRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->entity() && $this->user()->can('edit', $this->entity());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if (! $this->entity()) {
            return [];
        }

        return [
            'name' => 'required',
            'name' => sprintf('required|unique:expense_categories,name,%s,id,account_id,%s', $this->entity()->id, $this->user()->account_id),
        ];
    }
}
