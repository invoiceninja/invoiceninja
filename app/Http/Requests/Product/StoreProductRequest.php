<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Product;

use App\Http\Requests\Request;
use App\Models\Product;

class StoreProductRequest extends Request
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
        return [
            //'product_key' => 'required|unique:products,product_key,null,null,company_id,'.auth()->user()->companyId(),
            'cost' => 'numeric',
            'price' => 'numeric',
            'quantity' => 'numeric',
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (!isset($input['quantity']) || $input['quantity'] < 1) {
            $input['quantity'] = 1;
        }

        $this->replace($input);
    }
}
