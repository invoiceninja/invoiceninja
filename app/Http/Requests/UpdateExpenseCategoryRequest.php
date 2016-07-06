<?php namespace App\Http\Requests;

class UpdateExpenseCategoryRequest extends ExpenseCategoryRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
          return [
            'name' => 'required',
        ];
    }
}
