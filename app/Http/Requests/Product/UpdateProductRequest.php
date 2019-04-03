<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class UpdateProductRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {

        return auth()->user()->can('create', Product::class);

    }

    public function rules()
    {
            //when updating you need to ignore the column ID

        return [
            'product_key' => 'unique:products,product_key,'.$this->product->id.',id,company_id,'.auth()->user()->companyId(),
        ];
    }

}

