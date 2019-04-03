<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\Request;
use App\Models\Product;

class CreateProductRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', Product::Class);
    }

    public function rules() : array
    {
    	return [
    		'product_key' => 'required',
    	]
    }

}