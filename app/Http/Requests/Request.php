<?php

namespace App\Http\Requests;

use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class Request extends FormRequest
{
    use MakesHash;

    public function entity($class, $encoded_primary_key)
    {
        return $class::findOrFail($this->decodePrimaryKey($encoded_primary_key));
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

}
