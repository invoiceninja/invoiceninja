<?php namespace App\Http\Requests;

use Auth;
use App\Http\Requests\Request;
use Illuminate\Validation\Factory;

class UpdateUserRequest extends Request
{
    // Expenses 
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('edit', $this->entity());
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
