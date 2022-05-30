<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\PurchaseOrder;


use App\Http\Requests\Request;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends Request
{
    use MakesHash;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create', PurchaseOrder::class);
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [];

        $rules['client_id'] = 'required';


        $rules['number'] = ['nullable', Rule::unique('purchase_orders')->where('company_id', auth()->user()->company()->id)];
        $rules['discount']  = 'sometimes|numeric';
        $rules['is_amount_discount'] = ['boolean'];


        $rules['line_items'] = 'array';

        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }

}
