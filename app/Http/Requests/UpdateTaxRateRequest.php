<?php namespace App\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Validation\Factory;

class UpdateTaxRateRequest extends TaxRateRequest
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
            'name' => 'required',
            'rate' => 'required',
        ];
    }
}
